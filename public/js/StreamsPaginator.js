/**
 * Paginator component - used for posts (feed+profile) pagination
 */
"use strict";
/* global paginatorConfig */

var StreamsPaginator = {

    isFetching: false,
    nextPageUrl: '',
    prevPageUrl: '',
    currentPage: null,
    container: '',
    method: 'GET',
    fillTimer: null,

    /**
     * Initiates the component
     * @param route
     * @param container
     * @param method
     */
    init: function (route,container,method='GET') {
        StreamsPaginator.nextPageUrl = route;
        StreamsPaginator.prevPageUrl = paginatorConfig.prev_page_url;
        StreamsPaginator.currentPage = paginatorConfig.current_page;
        StreamsPaginator.container = container;
        StreamsPaginator.method = method;
    },

    /**
     * Loads (new) up paginated results
     * @param direction
     */
    loadResults: function (direction='next') {
        let url = StreamsPaginator.nextPageUrl;
        if(direction === 'prev'){
            url = StreamsPaginator.prevPageUrl;
        }
        if(StreamsPaginator.isFetching === true || !url){
            return false;
        }
        StreamsPaginator.isFetching = true;
        StreamsPaginator.toggleLoadingIndicator(true);
        $.ajax({
            type: StreamsPaginator.method,
            url: url,
            dataType: 'json',
            success: function(result) {
                if(result.success){

                    if(result.data.hasMore === false){
                        StreamsPaginator.unbindPaginator();
                    }
                    if(direction !== 'prev'){
                        StreamsPaginator.nextPageUrl = result.data.next_page_url;
                    }
                    else{
                        StreamsPaginator.prevPageUrl = result.data.prev_page_url;
                        $('.reverse-paginate-btn').find('button').removeClass('disabled');
                    }

                    if(result.data.prev_page_url === null){
                        $('.reverse-paginate-btn').fadeOut("fast", function() {});
                    }

                    // Appending the items & incrementing the counter
                    StreamsPaginator.appendPostResults(result.data.users, direction);
                    StreamsPaginator.isFetching = false;
                    if(direction !== 'prev' && result.data.hasMore !== false){
                        StreamsPaginator.scheduleViewportFill();
                    }
                }
                else{
                    // Handle error-ed requests
                    StreamsPaginator.isFetching = false;
                }
                StreamsPaginator.toggleLoadingIndicator(false);
            }
        });
    },

    /**
     * Toggles the loading indicator
     * @param loading
     */
    toggleLoadingIndicator: function(loading = false){
        if(loading === true){
            $('.posts-loading-indicator .spinner').removeClass('d-none');
        }
        else{
            $('.posts-loading-indicator .spinner').addClass('d-none');
        }
    },

    /**
     * Appends new posts to the feed container
     * @param posts
     * @param direction
     */
    appendPostResults: function(posts, direction = 'next'){
        // Building up the HTML array
        let htmlOut = [];
        let postIDs = [];
        $.map(posts,function (post) {
            htmlOut.push(post.html);
            postIDs.push(post.id);
        });

        // Appending the output
        if(direction === 'next'){
            $(StreamsPaginator.container).append(htmlOut.join('')).fadeIn('slow');
        }else{
            $(StreamsPaginator.container).prepend(htmlOut.join('')).fadeIn('slow');
        }
    },

    /**
     * Initiates infinite scrolling
     */
    initScrollLoad: function(){
        window.onscroll = function() {
            if (((window.innerHeight + window.scrollY + 2) * window.devicePixelRatio.toFixed(2)) >= document.body.offsetHeight * window.devicePixelRatio.toFixed(2)) {
                StreamsPaginator.loadResults();
            }
        };
        StreamsPaginator.scheduleViewportFill();
    },

    scheduleViewportFill: function(){
        window.clearTimeout(StreamsPaginator.fillTimer);
        StreamsPaginator.fillTimer = window.setTimeout(function () {
            StreamsPaginator.fillViewport();
        }, 80);
    },

    fillViewport: function(){
        if(StreamsPaginator.isFetching === true || !StreamsPaginator.nextPageUrl){
            return;
        }

        const documentHeight = Math.max(
            document.body.scrollHeight,
            document.body.offsetHeight,
            document.documentElement.clientHeight,
            document.documentElement.scrollHeight,
            document.documentElement.offsetHeight
        );
        const viewportHeight = window.innerHeight || document.documentElement.clientHeight;

        if(documentHeight <= viewportHeight + 120){
            StreamsPaginator.loadResults();
        }
    },

    /**
     * Unbinds the paginator infinite scrolling behaviour
     */
    unbindPaginator: function () {
        StreamsPaginator.nextPageUrl = '';
        window.clearTimeout(StreamsPaginator.fillTimer);
        window.onscroll = function() {};
    },

};
