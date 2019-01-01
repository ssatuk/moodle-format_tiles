// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Javascript Module to handle browser storage for format_tiles
 * Stores and retrieves course content and settings
 * e.g. which filter button do I have pressed
 *
 * @module browser_storage
 * @package course/format
 * @subpackage tiles
 * @copyright 2018 David Watson
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 3.3
 */
/*global localStorage, sessionStorage, setTimeout*/
/* eslint space-before-function-paren: 0 */

define(["jquery", "core/str", "core/notification"], function ($, str, Notification) {
    "use strict";

    var courseId;
    var userId;
    var storageEnabled = {
        local: false,
        session: false
    };
    var storageUserConsent = {
        GIVEN: "yes", // What to store in local storage to indicate consent granted.
        DENIED: "no", // Or to indicate consent denied.
        userChoice: null // The user's current choice - initially null as we have not yet checked local storage or asked user.
    };

    var localStorageKeyElements = {
        prefix: "mdl-",
        course: "mdl-course-",
        lastSection: "-lastSecId",
        content: "-content",
        lastUpdated: "-lastUpdated",
        userPrefStorage: "mdl-tiles-userPrefStorage",
        collapseSecZero: "-collapsesec0",
        user: "-user-",
        section: "-sec-"
    };

    var MAX_SECTIONS_TO_STORE;
    /**
     * The last visited section number will be stored with a key in the format
     * mdl-course-[courseid]-lastSecId
     * @returns {string} the key to use for this course
     */
    var encodeLastVistedSectionKeyName = function() {
        return localStorageKeyElements.course + courseId
            + localStorageKeyElements.user + userId
            + localStorageKeyElements.lastSection;
    };

    /**
     The last visited section's content will be stored with a key in the format
     * mdl-course-[courseid]-sec-[sectionid]-content
     * @param {number} sectionId the section Id we are interested in
     * @returns {string} the key to use for this course section's content
     */
    var encodeContentKeyName = function(sectionId) {
        return localStorageKeyElements.course + courseId
            + localStorageKeyElements.section + sectionId.toString()
            + localStorageKeyElements.user + userId
            + localStorageKeyElements.content;
    };

    /**
     * The last update time for the content for this section
     * will be stored with a key in the format
     * mdl-course-[courseid]-sec-[sectionid]-lastUpdated
     * @param {number} sectionId the section Id we are interested in
     * @returns {string} the key to use for this course section's content update time
     */
    var encodeContentLastUpdatedKeyName = function(sectionId) {
        return localStorageKeyElements.course + courseId
            + localStorageKeyElements.section + sectionId.toString()
            + localStorageKeyElements.user + userId
            + localStorageKeyElements.lastUpdated;
    };

    /**
     * Whether or not section zero is collapsed for this course/user
     * will be stored with a key in this format
     * @returns {string} the key to use
     */
    var collapseSecZeroKey = function() {
        return localStorageKeyElements.course + courseId
            + localStorageKeyElements.user + userId
            + localStorageKeyElements.collapseSecZero;
    };

    var encodeUserPrefStorageKey = function() {
        return localStorageKeyElements.userPrefStorage + localStorageKeyElements.user + userId;
    };

    /**
     * Check if the current key name is a last updated content key or not
     * The format used if this is true will be
     * mdl-course-[courseid]-sec-[sectionid]-lastUpdated
     * Check for this and return true if this key matches
     * @param {string} key the key to check
     * @returns {boolean} whether it matches or not
     */
    var isContentLastUpdatedKeyName = function(key) {
        return key.substring(0, 4) === localStorageKeyElements.prefix
            && key.substr(-12) === localStorageKeyElements.lastUpdated;
    };

    /**
     * Check if the user's browser supports localstorage or session storage
     * @param {String} localOrSession the type of storage we wish to check
     * @param {number} maxSectionsToStore how many sections the site admin has said we can store
     * @returns {boolean} whether or not storage is supported
     */
    var storageInitialCheck = function (localOrSession, maxSectionsToStore) {
        var storage;
        if (!maxSectionsToStore) {
            return false;
        }
        try {
            if (localOrSession === "local") {
                storage = localStorage;
            } else if (localOrSession === "session") {
                storage = sessionStorage;
            }
            if (typeof storage === "undefined") {
                return false;
            }
            storage.setItem("testItem", "testValue");
            if (storage.getItem("testItem") === "testValue") {
                storage.removeItem("testItem");
                return true;
            }
            return false;
        } catch (err) {
            return false;
        }
    };

    /**
     * Count how many course content items (sections) we have in session storage
     * i.e. filter all session storage keys down to only those which start with
     * mdl- and end with -lastUpdated and count how many
     * @returns {number} the number stored
     */
    var countStoredContentItems = function () {
        return (
            Object.keys(sessionStorage).filter(function (key) {
                return isContentLastUpdatedKeyName(key);
            })
        ).length;
    };

    /**
     * Store HTML from a section (or the landing page if sectionId is zero) into session storage
     * Considered using core/storagewrapper, core/sessionstorage and core/localstorage but they
     * don't contain much so implemented directly
     * @param {string} courseId
     * @param {number} sectionId
     * @param {String} html
     */
    var storeCourseContent = function (courseId, sectionId, html) {
        if (html && html !== "" && storageEnabled.session) {
            sessionStorage.setItem(encodeContentKeyName(sectionId), html);
            sessionStorage.setItem(
                encodeContentLastUpdatedKeyName(sectionId),
                Math.round(Date.now() / 1000).toString()
            );
        } else {
            // HTML is empty so remove from store if present.
            sessionStorage.removeItem(encodeContentKeyName(sectionId));
            sessionStorage.removeItem(encodeContentLastUpdatedKeyName(sectionId));
        }
    };

    /**
     * Decode a storage key in the format
     * mdl-course-2-sec-3-user-2-lastUpdated
     * i.e. course 2, section 3, user 2
     * @param {string} key the text value of key e.g. mdl-course-12-sec-7-lastUpdated
     * @return {object} json with key values
     */
    var decodeLastUpdatedKey = function (key) {
        var splitKey = key.split("-");
        if (isContentLastUpdatedKeyName(key)) {
            return {
                courseId: parseInt(splitKey[2]),
                sectionId: parseInt(splitKey[4]),
                userId: parseInt(splitKey[6]),
                title: splitKey[7]
            };
        } else {
            throw new Error("Invalid lastUpdated key");
        }
    };

    /**
     * Clean up items in local storage and session storage
     * For SESSION STORAGE, these will be course content HTML items or corresponding time records for them,
     * so to ensure we don't get too many, on each course access, we delete them if they are older than the threshold
     * This applies even if they relate to a different course to the one now being visited.
     * For LOCAL STORAGE, items will be very small (no HTML) so we only clear them if the user has selected to
     * clear browser storage. They include IDs of sections last visited in each course, whether section zero is collapsed etc
     * @param {number} contentDeleteMins how many minutes old a stored content HTML item must be, before it is be deleted here
     * @param {number} clearBrowserStorage if true, we are deleting all session and local storage on user command
     * @param {number} maxNumberToKeep how many items of HTML can be kept in store in total (evict the rest)
     */
    var cleanUp = function (contentDeleteMins, clearBrowserStorage, maxNumberToKeep) {
        // Clean localStorage first - only clear if we are clearing all browser storage.
        // Otherwise leave it (used for last visited section IDs etc).
        if (clearBrowserStorage) {
            Object.keys(localStorage).filter(function (key) {
                return key.substring(0, 4) === localStorageKeyElements.prefix && key !== encodeUserPrefStorageKey();
            }).forEach(function (item) {
                // Item does relate to this plugin.
                // It is not the user's preference about whether to use storage or not (keep that).
                localStorage.removeItem(item);
            });

            // Now clean sessionStorage (used for course content HTML).
            Object.keys(sessionStorage).filter(function (key) {
                // Filter to only keys relating to this plugin.
                return key.split("-")[0] === "mdl";
            }).forEach(function (itemKey) {
                // Item does relate to this plugin.
                if (isContentLastUpdatedKeyName(itemKey)) {
                    var params = decodeLastUpdatedKey(itemKey);
                    if (clearBrowserStorage) {
                        // Remove *all* items for this plugin regardless of age.
                        storeCourseContent(params.courseId, params.sectionId, null); // Empty last arg will mean deletion.
                    } else {
                        // Remove *stale* items for this plugin.
                        if (sessionStorage.getItem(itemKey) < Math.round(Date.now() / 1000) - contentDeleteMins * 60
                                || contentDeleteMins === 0) {
                            // Item is stale - older than contentDeleteMins settings.
                            // this key represents an item with a last update date older than the delete threshold.
                            storeCourseContent(params.courseId, params.sectionId, null); // Empty last arg will mean deletion.
                        }
                    }
                }
            });
        }
        // Now check if we still have too many items and if we do, remove the oldest.
        if (!clearBrowserStorage) {
            var lastUpdateKeys = Object.keys(sessionStorage).filter(function (item) {
                return isContentLastUpdatedKeyName(item);
            });
            if (lastUpdateKeys.length > maxNumberToKeep) {
                // We don't need this step if clearing whole browser storage as it is already cleared above.
                // get all the update times in order from newest to oldest.
                var lastUpdateTimes = lastUpdateKeys.map(function (key) {
                    return parseInt(sessionStorage[key]);
                }).sort();
                // Set a cut off time so that we only have maxNumberToKeep newer than the cut off.
                var cutOffTime = lastUpdateTimes[lastUpdateTimes.length - maxNumberToKeep];
                var params;
                // Remove course content for all items older than the cut off time.
                lastUpdateKeys.filter(function (key) {
                    return sessionStorage[key] < cutOffTime;
                }).forEach(function (expiredKey) {
                    params = decodeLastUpdatedKey(expiredKey);
                    storeCourseContent(params.courseId, params.sectionId, null); // Null will remove item.
                });
            }
        }
    };

    /**
     * Launch the window enabling the user to select whether we want to store data locally or not
     */
    var launchUserPreferenceWindow = function () {
        str.get_strings([
            {key: "datapref", component: "format_tiles"},
            {key: "dataprefquestion", component: "format_tiles"},
            {key: "yes"},
            {key: "no"}
        ]).done(function (s) {
            Notification.confirm(
                s[0],
                s[1],
                s[2],
                s[3],
                function () {
                    storageUserConsent.userChoice = storageUserConsent.GIVEN;
                    localStorage.setItem(encodeUserPrefStorageKey(), storageUserConsent.GIVEN);
                    storageEnabled.local = storageInitialCheck("local", MAX_SECTIONS_TO_STORE);
                    storageEnabled.session = storageInitialCheck("session", MAX_SECTIONS_TO_STORE);
                },
                function () {
                    storageUserConsent.userChoice = storageUserConsent.DENIED;
                    localStorage.setItem(encodeUserPrefStorageKey(), storageUserConsent.DENIED);
                    cleanUp(0, 1, 0);
                    storageEnabled.local = 0;
                }
            );
        });
    };

    /**
     * Set the last visited section for the user for this course
     * Used to reload that section on next visit
     * Data is just an integer for section if
     * Uses local storage not session storage so that it persists
     * @param {number} sectionNum the section number last visited
     */
    var setLastVisitedSection = function (sectionNum) {
        if (sectionNum && storageEnabled.local) {
            localStorage.setItem(encodeLastVistedSectionKeyName(), sectionNum.toString());
        } else {
            localStorage.removeItem(encodeLastVistedSectionKeyName());
        }
    };

    var Module = {

        init: function (course, maxContentSectionsToStore, isEditing, sectionNum,
                        storedContentDeleteMins, assumeDataStoreConsent, user) {
            courseId = course.toString();
            userId = user.toString();
            MAX_SECTIONS_TO_STORE = maxContentSectionsToStore;

             // Work out if we should be using local storage or not - does user want it and is it available.
             // Local is used for storing small items last sec visited ID etc.
             // Session is used for course content.
            storageUserConsent.userChoice = assumeDataStoreConsent === 1
                ? storageUserConsent.GIVEN
                : localStorage.getItem(encodeUserPrefStorageKey());
            if (storageUserConsent.userChoice === storageUserConsent.DENIED) {
                storageEnabled.local = false;
                storageEnabled.session = 0;
                cleanUp(0, 1, 0);
            } else {
                storageEnabled.local = storageInitialCheck("local", MAX_SECTIONS_TO_STORE);
                storageEnabled.session = storageInitialCheck("session", MAX_SECTIONS_TO_STORE);
            }

            $(document).ready(function () {
                if (assumeDataStoreConsent === '1') {
                    storageUserConsent.userChoice = storageUserConsent.GIVEN;
                }
                 // We do not know if if user is content for us to use local storage, so find out.
                if ((storageEnabled.local || storageEnabled.session) && storageUserConsent.userChoice === null) {
                    setTimeout(function () {
                        launchUserPreferenceWindow(maxContentSectionsToStore);
                    }, 500);
                }

                // If the user clicks the "Data preference" item in the navigation menu,
                // show them the dialogue box to re-enter their local storage choice.

                $('a[href*="datapref"]').click(function (e) {
                    e.preventDefault();
                    launchUserPreferenceWindow(maxContentSectionsToStore);
                });

                 // See format_tiles/completion.js for most of the actions related to togglecomoletion.
                $("#page").on("click", ".togglecompletion", function (e) {
                    if (storageEnabled.local) {
                        // Replace/remove related stored content.
                        // (Now inaccurate as show box incorrect box ticks and completion %).
                        storeCourseContent(courseId, 0, null);
                        var secId = $(e.currentTarget).attr("data-section");
                        setTimeout(function () {
                            // Wait to ensure that the new check box image is displayed.
                            // Then store the sec content including that change.
                            storeCourseContent(
                                courseId,
                                secId,
                                $("#section-" + secId).html()
                            );
                        }, 1000);
                    }
                });

                if (isEditing) {
                    // Teacher is editing now so not using JS nav but set their current section for when they stop editing.
                    setLastVisitedSection(sectionNum);
                    // Clear storage in case they just changed something.
                    cleanUp(0, 1, 0);
                    if (storageEnabled.session) {
                        storeCourseContent(courseId, sectionNum, null);
                    }
                }
                $("#page-content").on("click", ".tile", function () {
                    if (storageEnabled.session) {
                        // Evict unused HTML content from session storage to reduce footprint (after a delay).
                        if (countStoredContentItems() > maxContentSectionsToStore) {
                            setTimeout(function () {
                                cleanUp(storedContentDeleteMins, 0, maxContentSectionsToStore);
                            }, 2000);
                        }
                    }
                });
            });
        },

        storageEnabledSession: function () {
            return storageEnabled.session;
        },
        storageEnabledLocal: function () {
            return storageEnabled.local;
        },
        storageUserPreference: function () {
            return storageUserConsent.userChoice === storageUserConsent.GIVEN;
        },

        /**
         * Get the user's last visited section id for this course
         * @return {string|null} the section ID or null if none stored
         */
        getLastVisitedSection: function () {
            return localStorage.getItem(encodeLastVistedSectionKeyName());
        },

        /**
         * Retrieve HTML from session storage for this section
         * @param {number} courseId the id for this course
         * @param {number} sectionId id for this section
         * @return {String} the HTML
         */
        getCourseContent: function (courseId, sectionId) {
            return sessionStorage.getItem(encodeContentKeyName(sectionId));
        },

        /**
         * Check the age of any content we have stored for this course section
         * @param {number} courseId
         * @param {number} sectionId
         * @return {number|boolean} the age in seconds if we have content or false if none
         */
        getStoredContentAge: function (courseId, sectionId) {
            var storedTime = parseInt(
                sessionStorage.getItem(
                    encodeContentLastUpdatedKeyName(sectionId)
                )
            );
            if (storedTime) {
                return Math.round(Date.now() / 1000 - storedTime);
            } else {
                return false;
            }
        },

        /**
         * When user collapsed or expands section zero, record their choice in localStorage so
         * that it can be applied next time they visit
         * @param {string} status to be applied
         */
        setSecZeroCollapseStatus: function (status) {
            if (storageEnabled.local && storageUserConsent.userChoice === storageUserConsent.GIVEN) {
                if (status === "collapsed") {
                    localStorage.removeItem(collapseSecZeroKey());
                } else {
                    localStorage.setItem(collapseSecZeroKey(), "1");
                }
            }
        },
        /**
         * Get the last status of section zero for the present course from localStorage
         * @returns {boolean} whether collapsed or not
         */
        getSecZeroCollapseStatus: function () {
            return !!localStorage.getItem(collapseSecZeroKey());
        },

        storeCourseContent: function (courseId, sectionId, html) {
            // Return object ("public") access to the "private" method above.
            if (storageUserConsent.userChoice === storageUserConsent.GIVEN) {
                storeCourseContent(courseId, sectionId, html);
            }
        },

        cleanUp: function (contentDeleteMins, clearBrowserStorage, maxNumberToKeep) {
            // Return object ("public") access to the "private" method above.
            cleanUp(contentDeleteMins, clearBrowserStorage, maxNumberToKeep);
        },

        launchUserPreferenceWindow: function () {
            // Return object ("public") access to the "private" method above.
            launchUserPreferenceWindow();
        },

        setLastVisitedSection: function (sectionNum) {
            // Return object ("public") access to the "private" method above.
            if (storageUserConsent.userChoice === storageUserConsent.GIVEN) {
                setLastVisitedSection(sectionNum);
            }
        }
    };

    return Module;
});

// TODO consider using core/sessionstorage instead of this?