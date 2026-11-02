/**
 * Consent-gated purchase conversion delivery.
 * Attribution stays in memory until consent; the server owns the encrypted cookie.
 */
(function () {
    'use strict';

    const config = window.app && window.app.conversionTracking;
    if (!config || config.enabled !== true || typeof window.fetch !== 'function') {
        return;
    }

    let clickId = null;
    try {
        const parameter = config.clickIdParameter;
        if (typeof parameter === 'string' && /^[A-Za-z][A-Za-z0-9_]{0,63}$/.test(parameter)) {
            const candidate = new URL(window.location.href).searchParams.get(parameter);
            if (candidate && candidate.length <= 512 && /^[\x20-\x7e]+$/.test(candidate)) {
                clickId = candidate;
            }
        }
    } catch (error) {
        // Invalid URLs must not interfere with normal site navigation.
    }

    let generation = 0;
    let consented = false;
    let synchronized = false;
    let syncing = false;
    let syncRequested = false;
    let delivering = false;
    let activeRequest = null;
    let cancelSnippet = null;
    const activeContainers = new Set();

    function hasConsent() {
        try {
            return window.app.showCookiesBox === true
                && !!window.CookieConsent
                && typeof window.CookieConsent.acceptedCategory === 'function'
                && window.CookieConsent.acceptedCategory('marketing') === true;
        } catch (error) {
            return false;
        }
    }

    function isAuthenticated() {
        return !!(window.user && window.user.user_id);
    }

    function isCurrent(expectedGeneration) {
        return expectedGeneration === generation && consented && synchronized && hasConsent();
    }

    function stopDelivery() {
        if (activeRequest) {
            activeRequest.abort();
            activeRequest = null;
        }
        if (cancelSnippet) {
            cancelSnippet();
            cancelSnippet = null;
        }
        activeContainers.forEach(function (container) { container.remove(); });
        activeContainers.clear();
        delete window.purchaseConversion;
        delete window.subscriptionConversion;
    }

    async function post(url, body, signal) {
        const csrf = document.querySelector('meta[name="csrf-token"]');
        if (!csrf || typeof url !== 'string' || new URL(url, window.location.href).origin !== window.location.origin) {
            throw new Error('Conversion endpoint is unavailable.');
        }
        const response = await window.fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf.getAttribute('content')
            },
            body: JSON.stringify(body),
            signal: signal
        });
        if (!response.ok) {
            throw new Error('Conversion request was not accepted.');
        }
        return response.json();
    }

    async function synchronizeConsent() {
        syncRequested = true;
        if (syncing) {
            return;
        }
        syncing = true;
        try {
            // Serialize grants and withdrawals so a late grant cannot overwrite a revoke.
            while (syncRequested) {
                syncRequested = false;
                const currentGeneration = generation;
                const granted = hasConsent();
                const body = { granted: granted };
                if (granted && clickId) {
                    body.click_id = clickId;
                }
                try {
                    const result = await post(config.consentUrl, body);
                    if (currentGeneration !== generation || granted !== hasConsent()) {
                        syncRequested = true;
                        continue;
                    }
                    synchronized = result.enabled === true && result.consented === true && granted;
                    if (synchronized) {
                        clickId = null;
                    }
                } catch (error) {
                    synchronized = false;
                }
            }
        } finally {
            syncing = false;
        }
        if (synchronized) {
            deliverPending();
        }
    }

    function refreshConsent() {
        const accepted = hasConsent();
        if (accepted !== consented) {
            generation++;
            consented = accepted;
            synchronized = false;
            if (!accepted) {
                clickId = null;
                stopDelivery();
            }
        }
        synchronizeConsent();
    }

    function executeSnippet(code, expectedGeneration) {
        if (!code || !code.trim()) {
            return Promise.resolve();
        }
        if (typeof code !== 'string' || code.length > 20000) {
            return Promise.reject(new Error('Invalid conversion snippet.'));
        }
        const template = document.createElement('template');
        template.innerHTML = code;
        if (!template.content.querySelector('script, img')) {
            return Promise.reject(new Error('Conversion snippets must contain HTML script or image tags.'));
        }

        const container = document.createElement('div');
        container.hidden = true;
        container.setAttribute('aria-hidden', 'true');
        activeContainers.add(container);

        return new Promise(function (resolve, reject) {
            let finished = false;
            let timer = null;
            function finish(error) {
                if (finished) {
                    return;
                }
                finished = true;
                window.clearTimeout(timer);
                cancelSnippet = null;
                container.remove();
                activeContainers.delete(container);
                if (error) {
                    reject(error);
                } else {
                    resolve();
                }
            }
            cancelSnippet = function () { finish(new Error('Marketing consent was withdrawn.')); };
            timer = window.setTimeout(function () { finish(new Error('Conversion script timed out.')); }, 15000);

            // Recreate script elements: scripts inserted through innerHTML never execute.
            // Event fields are provided separately, never substituted into executable code.
            const nodes = Array.from(template.content.querySelectorAll('script, img'));
            let index = 0;
            function next() {
                if (finished) {
                    return;
                }
                if (!isCurrent(expectedGeneration)) {
                    finish(new Error('Marketing consent was withdrawn.'));
                    return;
                }
                if (index >= nodes.length) {
                    finish();
                    return;
                }
                const original = nodes[index++];
                const isScript = original.tagName.toLowerCase() === 'script';
                const node = document.createElement(isScript ? 'script' : 'img');
                Array.from(original.attributes).forEach(function (attribute) {
                    node.setAttribute(attribute.name, attribute.value);
                });
                if (isScript) {
                    node.textContent = original.textContent;
                    node.async = false;
                }
                const waitForLoad = (isScript && (node.hasAttribute('src') || node.type === 'module'))
                    || (!isScript && node.hasAttribute('src'));
                if (waitForLoad) {
                    node.onload = next;
                    node.onerror = function () { finish(new Error('Conversion resource could not load.')); };
                }
                container.appendChild(node);
                if (!waitForLoad) {
                    next();
                }
            }
            document.body.appendChild(container);
            next();
        });
    }

    async function deliverPending() {
        if (delivering || syncing || !synchronized || !hasConsent() || !isAuthenticated()
            || document.visibilityState === 'hidden' || config.method !== 'browser') {
            return;
        }
        delivering = true;
        const currentGeneration = generation;
        try {
            activeRequest = new AbortController();
            const result = await post(config.pendingUrl, {}, activeRequest.signal);
            activeRequest = null;
            const events = Array.isArray(result.events) ? result.events.slice(0, 5) : [];
            for (const event of events) {
                if (!isCurrent(currentGeneration)) {
                    break;
                }
                const payload = Object.freeze({
                    event_id: event.event_id,
                    transaction_id: event.transaction_id,
                    value: event.value,
                    currency: event.currency,
                    event_name: event.event_name,
                    purchase_type: event.purchase_type,
                    is_recurring: event.is_recurring === true
                });
                let success = false;
                try {
                    if (config.testMode === true || event.test_mode === true) {
                        // Dry runs must not dispatch GTM events or execute third-party code.
                        if (window.console && typeof window.console.info === 'function') {
                            window.console.info('Purchase conversion dry run', payload);
                        }
                    } else {
                        window.purchaseConversion = payload;
                        delete window.subscriptionConversion;
                        if (payload.purchase_type === 'subscription') {
                            // Keep existing subscription snippets working without labelling other sales as subscriptions.
                            window.subscriptionConversion = Object.freeze(Object.assign({}, payload, { event_name: 'subscription_purchase' }));
                        }
                        window.dispatchEvent(new CustomEvent('purchase-completed', { detail: payload }));
                        if (payload.purchase_type === 'subscription' && isCurrent(currentGeneration)) {
                            window.dispatchEvent(new CustomEvent('subscription-purchased', { detail: window.subscriptionConversion }));
                        }
                        if (!isCurrent(currentGeneration)) {
                            break;
                        }
                        await executeSnippet(event.code, currentGeneration);
                    }
                    success = true;
                } catch (error) {
                    // Acknowledgements contain no provider errors, URLs, or sensitive payloads.
                }
                if (!isCurrent(currentGeneration)) {
                    break;
                }
                await post(config.acknowledgeUrl, { id: event.id, claim_token: event.claim_token, success: success });
            }
        } catch (error) {
            // The server claims each event once. Never replay an uncertain dispatch.
        } finally {
            activeRequest = null;
            delivering = false;
        }
    }

    window.addEventListener('cc:onConsent', refreshConsent);
    window.addEventListener('cc:onChange', refreshConsent);
    window.addEventListener('pageshow', refreshConsent);
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState !== 'hidden') {
            refreshConsent();
        }
    });
    window.setInterval(function () {
        if (document.visibilityState === 'hidden' || !hasConsent() || !isAuthenticated()) {
            return;
        }
        if (!synchronized) {
            synchronizeConsent();
        } else {
            deliverPending();
        }
    }, 30000);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { window.setTimeout(refreshConsent, 0); }, { once: true });
    } else {
        window.setTimeout(refreshConsent, 0);
    }
}());
