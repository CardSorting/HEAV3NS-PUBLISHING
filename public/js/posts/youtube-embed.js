"use strict";

// The server uses the same URL fixtures. Only IDs leave this parser, never user HTML.
// eslint-disable-next-line no-unused-vars
var YouTubeEmbed = {
    hosts: ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com',
        'youtu.be', 'www.youtu.be', 'youtube-nocookie.com', 'www.youtube-nocookie.com'],

    videoIds: function (text, allowedHosts) {
        if(typeof text !== 'string') return [];
        if(allowedHosts !== null && allowedHosts !== undefined && !Array.isArray(allowedHosts)) return [];
        const videoIds = new Set();
        const candidates = text.matchAll(/(?:https?:\/\/|www\.)[^\s<>"'`]+/giu);
        for(const candidate of candidates){
            const previous = candidate.index > 0 ? text[candidate.index - 1] : '';
            if(previous && /[a-z0-9_@:/\\.-]/iu.test(previous)) continue;
            let url = candidate[0].replace(/[.,!?;:)\]}]+$/, '');
            if(/^www\./i.test(url)) url = 'https://' + url;
            const parts = YouTubeEmbed.parseUrl(url);
            if(!parts || (allowedHosts && allowedHosts.indexOf(parts.host) === -1)) continue;
            const videoId = YouTubeEmbed.videoId(parts);
            if(videoId) videoIds.add(videoId);
        }
        return Array.from(videoIds);
    },

    parseUrl: function (url) {
        if(url.indexOf('\\') !== -1) return null;
        // Avoid browser URL normalization of dot segments and encoded hosts/IDs.
        const parts = url.match(/^(https?):\/\/([^/?#]+)([^?#]*)(?:\?([^#]*))?(?:#.*)?$/i);
        if(!parts || parts[2].indexOf('@') !== -1) return null;
        const authority = parts[2].match(/^([^:]+)(?::([0-9]+))?$/);
        if(!authority) return null;
        const scheme = parts[1].toLowerCase();
        const host = authority[1].toLowerCase();
        if(YouTubeEmbed.hosts.indexOf(host) === -1) return null;
        if(authority[2] !== undefined && Number(authority[2]) !== (scheme === 'https' ? 443 : 80)) return null;
        return {host: host, path: parts[3], query: parts[4] || ''};
    },

    videoId: function (parts) {
        let videoId = null;
        if(parts.host === 'youtu.be' || parts.host === 'www.youtu.be'){
            const path = parts.path.match(/^\/([A-Za-z0-9_-]{11})\/?$/);
            if(path) videoId = path[1];
        } else if(parts.path === '/watch'){
            const values = [];
            let invalidKey = false;
            parts.query.split('&').forEach(function (parameter) {
                const separator = parameter.indexOf('=');
                const key = separator < 0 ? parameter : parameter.slice(0, separator);
                if(key === 'v') values.push(separator < 0 ? '' : parameter.slice(separator + 1));
                else {
                    const decodedKey = key.replace(/%([a-f0-9]{2})/gi, function (encoded, hex) {
                        return String.fromCharCode(parseInt(hex, 16));
                    });
                    if(decodedKey === 'v' || /^v\[/.test(decodedKey)) invalidKey = true;
                }
            });
            if(!invalidKey && values.length === 1) videoId = values[0];
        } else {
            const path = parts.path.match(/^\/(?:shorts|embed|live)\/([A-Za-z0-9_-]{11})\/?$/);
            if(path) videoId = path[1];
        }
        return typeof videoId === 'string' && /^[A-Za-z0-9_-]{11}$/.test(videoId) ? videoId : null;
    }
};
