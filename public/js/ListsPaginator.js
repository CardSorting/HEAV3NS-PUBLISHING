/**
 * Paginator component - used for lists and list members pagination
 */
"use strict";
/* global initTooltips */

var ListsPaginator = {

    isFetching: false,
    nextPageUrl: '',
    container: '',
    type: 'lists',
    searchQuery: '',
    searchTimer: null,
    fillTimer: null,
    request: null,

    /**
     * Initiates the component
     * @param config
     */
    init: function (config) {
        ListsPaginator.nextPageUrl = config.next_page_url;
        ListsPaginator.type = config.type;
        ListsPaginator.container = config.type === 'members' ? '.list-members-row' : '.lists-wrapper';
        ListsPaginator.searchQuery = config.search_query || '';

        if(config.hasMore){
            ListsPaginator.initScrollLoad();
        }

        if(ListsPaginator.type === 'members'){
            ListsPaginator.initMemberSearch();
            ListsPaginator.syncMemberSearchToggleState();
        }
    },

    /**
     * Loads paginated results.
     */
    loadResults: function () {
        if(ListsPaginator.isFetching === true || !ListsPaginator.nextPageUrl){
            return false;
        }

        return ListsPaginator.fetchResults(ListsPaginator.nextPageUrl);
    },

    /**
     * Fetches paginated result HTML.
     * @param url
     * @param replace
     */
    fetchResults: function (url, replace = false) {
        ListsPaginator.isFetching = true;
        ListsPaginator.toggleLoadingIndicator(true);

        if(ListsPaginator.request){
            ListsPaginator.request.abort();
        }

        ListsPaginator.request = $.ajax({
            type: 'GET',
            url: url,
            dataType: 'json',
            success: function(result) {
                if(result.success){
                    let items = ListsPaginator.type === 'members' ? result.data.users : result.data.lists;
                    if(replace){
                        ListsPaginator.replaceResults(items);
                    }
                    else{
                        ListsPaginator.appendResults(items);
                    }

                    ListsPaginator.nextPageUrl = result.data.next_page_url;

                    if(result.data.hasMore === false){
                        ListsPaginator.unbindPaginator();
                    }
                    else{
                        ListsPaginator.initScrollLoad();
                    }

                    if(typeof initTooltips !== 'undefined'){
                        initTooltips();
                    }
                }

                ListsPaginator.isFetching = false;
                ListsPaginator.request = null;
                ListsPaginator.toggleLoadingIndicator(false);
            },
            error: function (result) {
                if(result.statusText === 'abort'){
                    return;
                }
                ListsPaginator.isFetching = false;
                ListsPaginator.request = null;
                ListsPaginator.toggleLoadingIndicator(false);
            }
        });

        return true;
    },

    /**
     * Replaces the active result container with fresh rows/cards.
     * @param items
     */
    replaceResults: function(items){
        $(ListsPaginator.container).empty().removeAttr('style');
        let resultsCount = ListsPaginator.appendResults(items);

        if(ListsPaginator.type === 'members'){
            ListsPaginator.toggleMembersEmptyState(resultsCount > 0);
        }
    },

    /**
     * Appends rows/cards to the active list container.
     * @param items
     */
    appendResults: function(items){
        let htmlOut = [];
        $.map(items,function (item) {
            htmlOut.push(item.html);
        });

        let html = htmlOut.join('');
        if(ListsPaginator.type === 'lists' && $(ListsPaginator.container).find('.list-item').length > 0 && html.length){
            html = '<hr class="my-2">' + html;
        }

        $(ListsPaginator.container).append(html);

        return htmlOut.length;
    },

    /**
     * Initiates list member search controls.
     */
    initMemberSearch: function(){
        $('.list-members-search-toggle').off('click').on('click', function () {
            $('.list-members-search-wrapper').toggleClass('d-none');
            ListsPaginator.syncMemberSearchToggleState();
            if(ListsPaginator.isMemberSearchOpen()){
                $('.list-members-search-input').focus();
            }
        });

        $('.list-members-search-input').off('input keydown').on('input', function () {
            let query = $(this).val().trim();
            $('.list-members-search-clear').toggleClass('d-none', query.length === 0);
            clearTimeout(ListsPaginator.searchTimer);
            ListsPaginator.searchTimer = setTimeout(function () {
                ListsPaginator.searchMembers(query);
            }, 300);
        }).on('keydown', function (event) {
            if(event.key === 'Escape'){
                ListsPaginator.clearMemberSearch();
            }
        });

        $('.list-members-search-clear').off('click').on('click', function () {
            ListsPaginator.clearMemberSearch();
        });
    },

    /**
     * Clears list member search.
     */
    clearMemberSearch: function(){
        $('.list-members-search-input').val('');
        $('.list-members-search-clear').addClass('d-none');
        clearTimeout(ListsPaginator.searchTimer);
        ListsPaginator.searchMembers('');
    },

    /**
     * @returns {boolean}
     */
    isMemberSearchOpen: function(){
        return !$('.list-members-search-wrapper').hasClass('d-none');
    },

    /**
     * Syncs the active h-pill state with the search panel visibility.
     */
    syncMemberSearchToggleState: function(){
        $('.list-members-search-toggle.h-pill').toggleClass('active', ListsPaginator.isMemberSearchOpen());
    },

    /**
     * Searches list members server-side.
     * @param query
     */
    searchMembers: function(query){
        ListsPaginator.searchQuery = query;
        ListsPaginator.nextPageUrl = ListsPaginator.getSearchUrl(query);
        ListsPaginator.fetchResults(ListsPaginator.nextPageUrl, true);
    },

    /**
     * Builds the first-page URL for a member search query.
     * @param query
     * @returns {string}
     */
    getSearchUrl: function(query){
        let url = new URL(window.location.href);
        url.searchParams.delete('page');

        if(query.length){
            url.searchParams.set('query', query);
        }
        else{
            url.searchParams.delete('query');
        }

        return url.toString();
    },

    /**
     * Toggles the member grid empty state.
     * @param hasResults
     */
    toggleMembersEmptyState: function(hasResults){
        let emptyState = $('.list-members-empty-state');
        let templateSelector = ListsPaginator.searchQuery.length ? emptyState.data('empty-search-template') : emptyState.data('empty-default-template');
        let templateHtml = templateSelector ? ($(templateSelector).html() || '') : '';
        emptyState.html(templateHtml).toggleClass('d-none', hasResults);
        $('.list-members-row').removeAttr('style').toggleClass('d-none', !hasResults);
    },

    /**
     * Initiates infinite scrolling.
     */
    initScrollLoad: function(){
        $(window).off('scroll.listsPaginator').on('scroll.listsPaginator', function() {
            if (((window.innerHeight + window.scrollY + 2) * window.devicePixelRatio.toFixed(2)) >= document.body.offsetHeight * window.devicePixelRatio.toFixed(2)) {
                ListsPaginator.loadResults();
            }
        });
        ListsPaginator.scheduleViewportFill();
    },

    scheduleViewportFill: function(){
        window.clearTimeout(ListsPaginator.fillTimer);
        ListsPaginator.fillTimer = window.setTimeout(function () {
            ListsPaginator.fillViewport();
        }, 80);
    },

    fillViewport: function(){
        if(ListsPaginator.isFetching === true || !ListsPaginator.nextPageUrl){
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
            ListsPaginator.loadResults();
        }
    },

    /**
     * Unbinds paginator infinite scrolling.
     */
    unbindPaginator: function () {
        ListsPaginator.nextPageUrl = '';
        window.clearTimeout(ListsPaginator.fillTimer);
        $(window).off('scroll.listsPaginator');
    },

    /**
     * Toggles the loading indicator.
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

};
