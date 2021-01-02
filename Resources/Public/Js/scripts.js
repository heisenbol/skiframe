

document.addEventListener("DOMContentLoaded", function(event) {
    // attach click handler for show buttons
    let showButtons = document.querySelectorAll('.tx_skiframe_container .showButton');
    for (let count = 0; count < showButtons.length; count++) {
        showButtons[count].addEventListener('click', showContent);
    }

    function showContent(evt) {
        // find parent container with class tx_skiframe_container, which will hold the original content
        let parent = getClosest(evt.target, '.tx_skiframe_container');
        if (parent) {
            // get original content
            let originalMarkup = parent.getAttribute('data-original');

            if (!originalMarkup) {
                console.log('No original markup');
                // @TODO some kind of alert for user?
            }
            else {
                // replace the button and disclaimer with original markup. The possible child element of class tx_skiframe_content is replaced (this child element exists only if fullwidth constant is set to true
                parent.innerHTML = originalMarkup;
            }
        }
        else {
            console.log('Could not find parent container of show button');
            // @TODO some kind of alert for user?
        }
    }

    function getClosest (elem, selector) {
        // https://gomakethings.com/how-to-get-the-closest-parent-element-with-a-matching-selector-using-vanilla-javascript/
        // Element.matches() polyfill
        if (!Element.prototype.matches) {
            Element.prototype.matches =
                Element.prototype.matchesSelector ||
                Element.prototype.mozMatchesSelector ||
                Element.prototype.msMatchesSelector ||
                Element.prototype.oMatchesSelector ||
                Element.prototype.webkitMatchesSelector ||
                function(s) {
                    var matches = (this.document || this.ownerDocument).querySelectorAll(s),
                        i = matches.length;
                    while (--i >= 0 && matches.item(i) !== this) {}
                    return i > -1;
                };
        }

        // Get the closest matching element
        for ( ; elem && elem !== document; elem = elem.parentNode ) {
            if ( elem.matches( selector ) ) return elem;
        }
        return null;
    };
});


