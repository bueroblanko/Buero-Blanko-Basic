(function ($, w) {
    $(function () {
        /**********************************************************************************
		Divi - Disable Premade Layouts - Settings Panel Options
	**********************************************************************************/

        class ddplSettings {
            setProperties() {
                this.trigger = "a.et-pb-layout-buttons-load";

                this.triggerVB = "button.et-fb-button--toggle-add";

                this.triggerCard = "div.et-fb-page-creation-card-accent-green";
            } // end setProperties

            constructor(self = this) {
                this.setProperties();

                let config = { childList: true },
                    target = document.querySelector("body"),
                    observer = new MutationObserver((mutations) => {
                        mutations.forEach((mutation) => {
                            if (
                                mutation.type === "childList" &&
                                ($(self.trigger).length > 0 ||
                                    $(self.triggerVB).length > 0 ||
                                    $(self.triggerCard).length > 0)
                            ) {
                                observer.disconnect();

                                self.bindEvents();
                            }
                        });
                    });

                observer.observe(target, config);
            } // end constructor

            visualBuilder() {
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

                        setTimeout(() => {
                            if ($("body").hasClass("no-et-layouts"))
                                $("a.existing_pages")[0].click();
                            else $("a.modules_library")[0].click();

                            iframe.addClass("show");
                        }, 100);
                    } // end if
                }, 10); // end setInterval
            } // end visualBuilder

            bindEvents(self = this) {
                $("#normal-sortables").on("mousedown", this.trigger, () => {
                    let checkForPanel = setInterval(() => {
                        var timeout = setTimeout(() => {
                            clearInterval(checkForPanel);
                        }, 10000);

                        let loader = $("#et_pb_loading_animation"),
                            iframe = $("div.et_pb_modal_settings").find(
                                "iframe"
                            );

                        if (
                            iframe.length > 0 &&
                            "none" == loader.css("display")
                        ) {
                            clearInterval(checkForPanel);

                            clearTimeout(timeout);

                            setTimeout(() => {
                                if ($("body").hasClass("no-et-layouts"))
                                    $(
                                        '[data-open_tab="et-pb-existing_pages-tab"]'
                                    )
                                        .children("a")
                                        .trigger("click");
                                else
                                    $(
                                        '[data-open_tab="et-pb-saved-modules-tab"]'
                                    )
                                        .children("a")
                                        .trigger("click");

                                iframe.addClass("show");
                            }, 100);
                        } // end if
                    }, 10); // end setInterval
                });

                $(this.triggerVB).on("mousedown click", (e) => {
                    self.visualBuilder();
                });

                $(this.triggerCard).on("mousedown click", (e) => {
                    self.visualBuilder();
                });
            } // end bindEvents
        } // end class ddplSettings

        new ddplSettings();
    });
})(jQuery, window);
