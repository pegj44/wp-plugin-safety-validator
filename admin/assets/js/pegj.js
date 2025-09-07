class Pegj {
    /**
     * @param {Object} config - Map of selector -> item config
     *   - allowMultiple?: boolean (default: true)
     *   - beforeSend?: (req, meta) => (void|false|Promise<void|false>)
     * @param {Object} options
     * @param {string} [options.url] - Default URL for requests (can be overridden per item)
     * @param {string} [options.method='POST'] - Default HTTP method
     * @param {'json'|'form'} [options.encode='json'] - How to send body
     * @param {EventTarget} [options.eventTarget=document] - Where to listen/dispatch custom events
     * @param {Object} [options.fetchOptions={}] - Extra fetch options (headers, credentials, etc.)
     * @param {Object|Function} [options.defaultData={}] - Data merged into every request
     */
    constructor(config, options = {}) {
        this.config = config || {};
        this.options = {
            method: 'POST',
            encode: 'form',
            eventTarget: document,
            fetchOptions: {},
            url: pegj_ajax.ajax_url,
            defaultData: {},
            ...options,
        };
        this._listeners = [];
        // Map<selector, WeakMap<Element, AbortController>>
        this._inflight = new Map();
        this._init();
    }

    _init() {
        Object.entries(this.config).forEach(([selector, item]) => {
            const elements = Array.from(document.querySelectorAll(selector));
            if (!elements.length) return;

            // DOM trigger (e.g., 'click')
            if (item.trigger) {
                const shouldPrevent = item.preventDefault !== false; // default true
                elements.forEach((el) => {
                    const handler = (evt) => {
                        if (shouldPrevent && evt?.preventDefault) evt.preventDefault();
                        this._handleRequest({ selector, el, evt, item });
                    };
                    el.addEventListener(item.trigger, handler);
                    this._listeners.push({ el, type: item.trigger, handler });
                });
            }

            // Custom event trigger(s)
            const customTriggers = this._normalizeToArray(item.eventTrigger);
            if (customTriggers.length) {
                const shouldPreventCustom = item.preventDefault !== false; // default true
                customTriggers.forEach((evtName) => {
                    const target = this.options.eventTarget;
                    const handler = (evt) => {
                        if (shouldPreventCustom && evt?.preventDefault) evt.preventDefault();
                        if (evt?.detail?.selector && evt.detail.selector !== selector) return;
                        const targets = evt?.detail?.elements || elements;
                        targets.forEach((el) => this._handleRequest({ selector, el, evt, item }));
                    };
                    target.addEventListener(evtName, handler);
                    this._listeners.push({ el: target, type: evtName, handler });
                });
            }
        });
    }

    destroy() {
        this._listeners.forEach(({ el, type, handler }) => el.removeEventListener(type, handler));
        this._listeners = [];
        // Abort anything in-flight
        this._inflight.forEach((wm) => {
            wm && wm instanceof WeakMap && wm.forEach?.((ctrl) => ctrl.abort?.());
        });
        this._inflight.clear();
    }

    _normalizeToArray(value) {
        if (!value) return [];
        return Array.isArray(value) ? value : [value];
    }

    _getInflightMapForSelector(selector) {
        let wm = this._inflight.get(selector);
        if (!wm) {
            wm = new WeakMap();
            this._inflight.set(selector, wm);
        }
        return wm;
    }

    async _handleRequest(ctx) {
        const { selector, el, evt, item } = ctx;

        // Resolve URL/method/encode
        const resolvedUrl = item.url || this.options.url;
        if (!resolvedUrl) {
            console.error(`[Pegj] Missing URL for selector "${selector}"`);
            return;
        }
        const resolvedMethod = (item.method || this.options.method || 'POST').toUpperCase();
        const resolvedEncode = item.encode || this.options.encode || 'json';
        const allowMultiple = item.allowMultiple !== undefined ? !!item.allowMultiple : true;

        // Build data (defaultData → item.data → event payload)
        let defaultData = {};
        try {
            defaultData =
                typeof this.options.defaultData === 'function'
                    ? await this.options.defaultData(el, evt)
                    : (this.options.defaultData || {});
        } catch (err) {
            console.error('[Pegj] Error building defaultData:', err);
        }

        let baseData = {};
        try {
            baseData =
                typeof item.data === 'function' ? await item.data(el, evt) : (item.data || {});
        } catch (err) {
            console.error('[Pegj] Error building data:', err);
        }

        baseData.action = pegj_ajax.handle +'_'+ item.handle;
        baseData.nonce = pegj_ajax.nonce[item.handle];

        const eventPayload = evt?.detail?.payload || {};
        const mergedData = { ...defaultData, ...baseData, ...eventPayload };

        // beforeSend — AFTER trigger & data merge, BEFORE fetch
        const req = {
            url: resolvedUrl,
            method: resolvedMethod,
            encode: resolvedEncode,
            data: mergedData,
            headers: { ...(this.options.fetchOptions.headers || {}) },
            fetchOptions: { ...(this.options.fetchOptions || {}) },
            cancel: false,
        };

        if (typeof item.beforeSend === 'function') {
            try {
                const maybeResult = await item.beforeSend(req, { element: el, selector, event: evt, item });
                if (maybeResult === false || req.cancel === true) {
                    if (typeof item.error === 'function') {
                        item.error({ canceled: true }, { element: el, selector, event: evt });
                    }
                    return;
                }
            } catch (hookErr) {
                console.error('[Pegj] beforeSend() error:', hookErr);
                if (typeof item.error === 'function') {
                    item.error(hookErr, { element: el, selector, event: evt });
                }
                return;
            }
        }

        // Concurrency control (allowMultiple / abort previous)
        let controller = null;
        if (!allowMultiple) {
            const map = this._getInflightMapForSelector(selector);
            const prev = map.get(el);
            if (prev) {
                // Abort previous request for this selector+element
                try { prev.abort(); } catch (_) {}
            }
            controller = new AbortController();
            map.set(el, controller);
        }

        // Build fetch options from req (incl. signal if we created controller)
        const fetchOpts = {
            method: req.method,
            ...req.fetchOptions,
            headers: { ...(req.headers || {}) },
        };
        if (controller) fetchOpts.signal = controller.signal;

        if (req.encode === 'json') {
            fetchOpts.headers['Content-Type'] = 'application/json';
            fetchOpts.body = JSON.stringify(req.data);
        } else if (req.encode === 'form') {
            const form = new URLSearchParams();
            Object.entries(req.data || {}).forEach(([k, v]) => {
                if (Array.isArray(v)) v.forEach((vv) => form.append(k, String(vv)));
                else form.append(k, v != null ? String(v) : '');
            });
            fetchOpts.body = form;
            if (fetchOpts.headers['Content-Type'] === 'application/json') {
                delete fetchOpts.headers['Content-Type'];
            }
        }

        // Send
        try {
            const res = await fetch(req.url, fetchOpts);

            // 7) Parse response
            let payload;
            const ct = res.headers.get('content-type') || '';
            if (ct.includes('application/json')) {
                payload = await res.json();
            } else {
                payload = { data: { html: await res.text() } };
            }

            if (!res.ok) throw { status: res.status, payload };

            // 8) Optional output
            if (item.output && payload?.data?.html != null) {
                const outEl = document.querySelector(item.output);
                if (outEl) outEl.innerHTML = payload.data.html;
            }

            // 9) Fire createTrigger(s)
            const createTriggers = this._normalizeToArray(item.createTrigger);
            createTriggers.forEach((evtName) => {
                this.options.eventTarget.dispatchEvent(
                    new CustomEvent(evtName, {
                        detail: { selector, element: el, response: payload },
                    })
                );
            });

            // 10) Success callback
            if (typeof item.success === 'function') {
                try {
                    item.success(payload, { element: el, selector, event: evt });
                } catch (cbErr) {
                    console.error('[Pegj] success() callback error:', cbErr);
                }
            }
        } catch (error) {
            // Ignore silent aborts from allowMultiple=false replacement
            if (error?.name === 'AbortError') {
                // Optionally, you could call item.error({ aborted: true }, meta) here.
                return;
            }
            if (typeof item.error === 'function') {
                try {
                    item.error(error, { element: el, selector, event: evt });
                } catch (cbErr) {
                    console.error('[Pegj] error() callback error:', cbErr);
                }
            } else {
                console.error('[Pegj] Request failed:', error);
            }
        } finally {
            // Clear inflight if we're still the active controller
            if (!allowMultiple && controller) {
                const map = this._getInflightMapForSelector(selector);
                if (map.get(el) === controller) {
                    map.delete(el);
                }
            }
        }
    }
}
