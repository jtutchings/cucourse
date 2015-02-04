var timedelay = 5000;
function setNewsRotationTime(Y, timelength) {

    timedelay = timelength;
    return timedelay;
}

YUI().use('tabview', 'node', 'datasource-function', 'datasource-polling', 'dom', 'event', 'event-mouseenter', function(Y) {

    // Display the current, previous etc as tabs.
    var tabview = new Y.TabView({srcNode: '#modulelist'});
    tabview.render();

    // Cycle the news.
    var cucourseNewsItems = Y.all('.cucourseNewsItems li');

    function tog() {

        if (typeof tog.counter === 'undefined') {
            tog.counter = 0;
        } else {
            tog.counter++;

            if (tog.counter === cucourseNewsItems.size()) {
                tog.counter = 0;
            }
        }

        return tog.counter;
    }

    var handleBoxClick = function(e) {

        var i = tog();

        if (cucourseNewsItems.item(i) !== null) {
            cucourseNewsItems.hide();
            cucourseNewsItems.item(i).show(true);
        } else {

        }

    }, newsStories = new Y.DataSource.Function({source: handleBoxClick}),
    request = {
        callback: {
            success: function(e) {
            },
            failure: function(e) {
                alert("Could not retrieve data:" + e.error.message);
            }
        }
    },
    id = newsStories.setInterval(parseInt(timedelay), request);

    // Toggle the table of contents.

    var node = Y.one('#cucourseNewsTOCTrigger');

    if (node) {
        node.on("click", toggleTOCon);
    }

    var tocnode = Y.one("#cucourseNewsItemsTOC");
    if (tocnode) {
        tocnode.hide();
    }

    function toggleTOCon(e) {

        tocnode = Y.one("#cucourseNewsItemsTOC");
        newsnode = Y.one("#cucourseNewsContent");

        if (tocnode.getComputedStyle('display') === 'none') {
            tocnode.show();
            newsnode.hide();
        } else {

            tocnode.hide();
            newsnode.show();

        }

    }// end function.

});
