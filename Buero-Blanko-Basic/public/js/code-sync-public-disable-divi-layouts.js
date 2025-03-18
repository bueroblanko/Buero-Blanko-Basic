(function ($, w) {
    $(function () {
        /**********************************************************************************
		Divi - Disable Premade Layouts - Settings Panel Options
	**********************************************************************************/

        class ddplSettings {
            setProperties() {
                this.triggerVB = "button.et-fb-button--toggle-add";

                this.triggerCard =
                    "div.et-fb-page-creation-card-clone_existing_page";
            } // end setProperties

            constructor(self = this) {
                this.setProperties();

                let config = { childList: true },
                    target = document.querySelector("body"),
                    observer = new MutationObserver((mutations) => {
                        mutations.forEach((mutation) => {
                            if (
                                (mutation.type === "childList" &&
                                    $(self.triggerVB).length > 0) ||
                                $(self.triggerCard).length > 0
                            ) {
                                observer.disconnect();

                                self.bindEvents();
                            }
                        });
                    });

                observer.observe(target, config);
            } // end constructor

            visualBuilder(tabNum) {
                let checkForPanel = setInterval(() => {
                    var timeout = setTimeout(() => {
                        clearInterval(checkForPanel);
                    }, 10000);

                    let panel = $("#et-fb-settings-column"),
                        loader = panel.find("div.et-fb-preloader"),
                        iframe = panel.find("iframe");

                    if (
                        iframe.length > 0 &&
                        !loader.hasClass("et-fb-preloader__loading")
                    ) {
                        clearInterval(checkForPanel);

                        clearTimeout(timeout);

                        setTimeout(() => {
                            if (2 === tabNum) $("a.existing_pages")[0].click();
                            else $("a.modules_library")[0].click();

                            iframe.addClass("show");
                        }, 100);
                    } // end if
                }, 10); // end setInterval
            } // end visualBuilder

            bindEvents(self = this) {
                $(this.triggerVB).on("mousedown", (e) => {
                    self.visualBuilder(1);
                });

                $(this.triggerCard).on("mousedown", (e) => {
                    self.visualBuilder(2);
                });
            } // end bindEvents
        } // end class ddplSettings

        new ddplSettings();
    });
})(jQuery, window);
