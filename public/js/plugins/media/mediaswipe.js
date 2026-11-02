/*
	MediaSwipe version 1.0.0
	Description: Extension of PhotoSwipe allowing support of other medias like videos.
	License    : MIT license

	Copyright 2017 Joël VALLIER (joel.vallier@gmail.com)

	Permission is hereby granted, free of charge, to any person obtaining a copy
	of this software and associated documentation files (the "Software"), to deal
	in the Software without restriction, including without limitation the rights
	to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
	of the Software, and to permit persons to whom the Software is furnished to do so,
	subject to the following conditions:

	The above copyright notice and this permission notice shall be included in all
	copies or substantial portions of the Software.

	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
	INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A
	PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
	HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
	OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
	SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/
"use strict";
/* global debug, PhotoSwipe */

// MediaSwipe extension
// eslint-disable-next-line no-unused-vars
function MediaSwipe(element, options, items, params) {
    var closed = false;
    // debug
    var log = function(img, str, param) {
        if (debug) {
            // eslint-disable-next-line no-console
            if (param) console.log(img.id+' '+str, param);
            // eslint-disable-next-line no-console
            else console.log(img.id+' '+str);
        }
    };

    var createElement = function(item, type, prefix, classes, params) {
        // create element and set id
        var img = pswp.framework.createEl(classes, type);
        img.id = prefix+'-'+(1+pswp.items.indexOf(item));

        // set params
        if (params) {
            for (var prop in params) {
                img[prop] = params[prop];
            }
        }

        // return element
        return img;
    };

    // New createImage
    var createImage = function(item, classes, onload, onerror) {
        // create element
        classes += ' no-long-press';
        var img = createElement(item, 'img', 'image', classes);

        // get image size after loading
        if ((item.w < 1) || (item.h < 1)) {
            var photo = null;
            photo = new Image();
            photo.onload = function() {
                // set image width/height
                item.w = photo.width;
                item.h = photo.height;
                log(img, 'Dynamic size '+item.w+'x'+item.h);

                // reinitialize current item
                pswp.updateSize();
            };
            photo.onerror = function() {
                log(img, 'Load error');
            };
            photo.src = item.src;
        } else {
            log(img, 'Size '+item.w+'x'+item.h);
        }

        // set callbacks
        if (onload) img.onload = onload;
        if (onerror) img.onerror = onerror;

        // start image loading
        img.src = item.src;

        // export image
        this.img = img;
    };

    // Keep neighboring YouTube slides inert until the viewer selects them.
    var createYouTubeVideo = function(item, classes, onload, onerror) {
        var img = createElement(item, 'div', 'youtube', classes+' pswp__youtube-media');
        var iframe = null;
        var disposed = false;
        var valid = /^[A-Za-z0-9_-]{11}$/.test(item.youtubeId || '') &&
            item.src === 'https://www.youtube-nocookie.com/embed/'+item.youtubeId;

        // The holder is ready immediately; player loading does not block gallery navigation.
        var readyTimer = setTimeout(function() {
            if (closed || disposed) return;
            if (valid && onload) onload();
            if (!valid && onerror) onerror();
        }, 0);

        this.start = function() {
            if (!valid || disposed || closed || !pswp || item !== pswp.currItem || iframe) return;

            iframe = document.createElement('iframe');
            iframe.title = item.title || 'YouTube video';
            iframe.setAttribute('allow', 'encrypted-media; fullscreen; picture-in-picture');
            iframe.setAttribute('allowfullscreen', '');
            iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
            iframe.setAttribute('frameborder', '0');
            iframe.style.width = '100%';
            iframe.style.height = '100%';
            iframe.style.display = 'block';
            iframe.src = 'https://www.youtube-nocookie.com/embed/'+item.youtubeId+'?autoplay=0&playsinline=1&origin='+encodeURIComponent(window.location.origin);
            img.appendChild(iframe);
        };

        this.stop = function() {
            if (!iframe) return;
            // Unloading the frame stops playback without an extra API script or background player.
            iframe.removeAttribute('src');
            iframe.remove();
            iframe = null;
        };

        this.destroy = function() {
            disposed = true;
            clearTimeout(readyTimer);
            this.stop();
        };

        this.img = img;
    };

    // Support of standard videos
    var createVideo = function(item, classes, onload, onerror) {
        // HTML5 API Reference:
        // https://www.w3schools.com/tags/ref_av_dom.asp
        classes += ' pswp_video no-long-press';
        // create element
        var img = createElement(item, 'video', 'video', classes, {preload: 'auto', controls: 1, controlsList: "nodownload"});

        // clear player and set local callbacks
        img.oncanplay = function() {
            // log event
            log(img, 'Player ready');

            // get image size after loading
            if ((item.w < 1) || (item.h < 1)) {
                // set image width/height
                item.w = img.videoWidth;
                item.h = img.videoHeight;
                log(img, 'Dynamic size '+item.w+'x'+item.h);

                // reinitialize current item
                pswp.updateSize();
            } else {
                // log size
                log(img, 'Size '+item.w+'x'+item.h);
            }

            // workaround (delay onload else video doesn't start)
            if (onload) setTimeout(function() { onload(); }, 500);
        };
        img.onerror = function() {
            log(img, 'Error');
            if (onerror) onerror();
        };

        // define start and stop methods
        this.start = function() { img.play(); };
        this.stop = function() { img.pause(); };

        // start video loading
        img.src = item.src;
        this.img = img;

    };

    var createAudio = function(item, classes, onload, onerror) {
        // HTML5 API Reference:
        // https://www.w3schools.com/tags/ref_av_dom.asp
        classes += ' pswp_audio no-long-press';

        // create element
        var img = createElement(item, 'audio', 'audio', classes, {preload: 'auto', controls: 1, controlsList: "nodownload"});

        // clear player and set local callbacks
        img.oncanplay = function() {
            // log event
            log(img, 'Player ready');

            // get image size after loading
            // if ((item.w < 1) || (item.h < 1)) {
            // set image width/height
            item.w = '200';
            item.h = '50';
            log(img, 'Dynamic size '+item.w+'x'+item.h);

            // reinitialize current item
            pswp.updateSize();
            // } else {
            // log size
            log(img, 'Size '+item.w+'x'+item.h);
            // }

            // workaround (delay onload else video doesn't start)
            if (onload) setTimeout(function() { onload(); }, 500);
        };
        img.onerror = function() {
            log(img, 'Error');
            if (onerror) onerror();
        };

        // define start and stop methods
        this.start = function() { img.play(); };
        this.stop = function() { img.pause(); };

        // start video loading
        img.src = item.src;

        // export image
        this.img = img;
    };

    // ececute a function on items at current index +/- pos
    var execute = function(pos, fct) {
        if (pswp.items.length > (2*(pos-1)+1)) {
            var id = pswp.items.indexOf(pswp.currItem);
            var idx = [((id+pos) % pswp.items.length), ((id+pswp.items.length-pos) % pswp.items.length)];
            idx.forEach(function(id) {
                if (pswp.items[id].content) fct(id);
            });
        }
    };

    // stop, start and clear methods
    var start = function(item) {
        if (!closed && item && item.content) {
            log(item.content.img, 'Start');
            item.content.start();
        }
    };

    var stop = function(item) {
        if (item && item.content) {
            log(item.content.img, 'Stop');
            item.content.stop();
        }
    };

    var clear = function(item) {
        if (item && item.content) {
            log(item.content.img, 'Cleared');
            if (item.content.destroy) item.content.destroy();
            item.content = null;
        }
        if (item && item.mediaType === 'youtube') {
            item.loaded = item.loading = item.imageAppended = false;
        }
    };

    // generic create media
    var _createMedia = function(item, classes, onload, onerror) {
        var media = item.content || null;
        if (media) {
            // return created media
            log(media.img, 'Returned');
            // set callback
            if (onload) setTimeout(function() {
                if (item.mediaType !== 'youtube' || (!closed && item.content === media)) onload();
            }, item.mediaType === 'youtube' ? 0 : 500);
        } else {
            if (item.mediaType === 'youtube') {
                media = new createYouTubeVideo(item, classes, onload, onerror);
            }
            else if (/\.(mp4|ogg|webm)$/i.test(item.src)) {
                // YouTube video
                media = new createVideo(item, classes, onload, onerror);
            }
            else if(/\.(mp3|wav)$/i.test(item.src)){
                media = new createAudio(item, classes, onload, onerror);
            }
            else{
                media = new createImage(item, classes, onload, onerror);
            }


            // set default start and stop methods
            if (!media.start) media.start = function(){};
            if (!media.stop) media.stop = function(){};

            // log creation and save media
            log(media.img, 'Created'+(onload ? ' with callback' : ' without callback'));

            // Alternative way of wrapping up video/audio elements > Breaks autoplay and swiper behaviour
            // if(/\.(mp3|wav)$/i.test(item.src) || /\.(mp4|ogg|webm)$/i.test(item.src)){
            //     var cln = media.img.cloneNode(true);
            //     var wrapper = document.createElement('div')
            //     wrapper.setAttribute('class','h-100 w-100 d-flex justify-content-center align-items-center video-wrapper')
            //     wrapper.appendChild(cln)
            // }
            // media.img = wrapper;
            item.content = media;
        }

        // return media
        return media.img;
    };

    // listeners
    this.listen = function(evt, fct) {
        return pswp.listen(evt, fct);
    };

    // MediaSwipe initialisation
    this.init = function() {
        return pswp.init();
    };

    // log start
    // eslint-disable-next-line no-console
    if (debug) console.log('MediaSwipe Starts');

    // create instance
    var pswp = new PhotoSwipe(element, options, items, params);

    // redefine media creation (non standard API)
    pswp.setCustomMedia(_createMedia);

    pswp.listen('parseVerticalMargin', function(item) {
        if (!pswp || item.mediaType !== 'youtube') return;
        // Use the viewer width; PhotoSwipe fits the player into the available height.
        // Narrow screens need more height than 16:9 to keep YouTube controls usable.
        item.w = pswp.viewportSize.x || window.innerWidth || 640;
        item.h = Math.max(200, Math.round(item.w * 9 / 16));
    });

    // connect listeners
    pswp.listen('close', function() {
        closed = true;
        // stop current media
        stop(pswp.currItem);
        clear(pswp.currItem);
        execute(1, function(id) {
            clear(pswp.items[id]);
        });
        pswp.items.forEach(function(item) {
            if (item.mediaType === 'youtube') clear(item);
        });
        // log
        // eslint-disable-next-line no-console
        if (debug) console.log('MediaSwipe Closed');
    });
    pswp.listen('destroy', function() {
        closed = true;
        if (pswp) {
            pswp.items.forEach(function(item) {
                if (item.mediaType === 'youtube') clear(item);
            });
        }
        element.classList.remove('pswp--youtube-active');
        pswp = null;
    });
    pswp.listen('beforeChange', function() {
        if (!pswp || closed) return;
        pswp.items.forEach(function(item) {
            if (item.mediaType === 'youtube' && item !== pswp.currItem) stop(item);
        });
    });
    pswp.listen('afterChange', function(param) {
        if (!pswp || closed) return;
        element.classList.toggle('pswp--youtube-active', pswp.currItem.mediaType === 'youtube');
        // stop and clear uneeded videos
        execute(2, function(id) {
            clear(pswp.items[id]);
        });
        execute(1, function(id) {
            stop(pswp.items[id]);
        });
        // switch media
        if (!param) {
            // eslint-disable-next-line no-unused-vars
            let a = pswp.currItem.container.childNodes;
            start(pswp.currItem);
        }
        // Alternative way of wrapping up video/audio holders
        // $('.pswp_video, .pswp_audio').wrap('<div class="h-100 w-100 d-flex justify-content-center align-items-center"></div>');
    });

}
