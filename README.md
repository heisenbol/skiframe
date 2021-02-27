# skiframe
Filters iframes and scripts in HTML Content Elements and replaces them with placeholders that need to be activated in order to show the original iframe/script. Optionally disallows script tags. Needs fluid_styled_content. Feedback welcome!

This extension should be considered experimental. Feedback is welcome!

## How it works
The idea is to allow TYPO3 Editors to use the HTML content element to embed external iframes and scripts (e.g. youtube videos, twitter timelines), without having to care about GDPR violations.

The extensions examines the markup of the content element, and if it finds an iframe or script tag, it is replaced with a disclaimer message. If the user explicitely activates the content, the placeholder is removed, and the original content is shown (e.g. the embedded video is shown or the scripts injected).  

For iframes, the placeholder has the same size as the iframe. For scripts, the placeholder occupies a default size.

By adding a special HTML comment `<!-- NO_SKIFRAME -->` into the content element, the filter can be disabled. Through a constant, this functionality can be disabled (i.e. do not allow editors to disable the filter).

Script tags can be disabled altogether. Various other options allow fine-tuning of the behavior of the extension.

## How to install
Install the extension through the extension manager and add it's Typoscript Template into the Includes list of your main template.


## Configuration
The following Typoscript Constants can be used to fine-tune the behavior of the extension: 

All constants have a prefix of `plugin.tx_skiframe.settings.`

| News        | Default | Description |
| ----------- |---------------- | ------------------------ |
| `nocss`                 | `0`   | Set to 1 to exclude the extension's css file |
| `fullwidth`             | `0`  | Set to 1 to enlarge iframes to full width of the container, keeping the aspect ratio |
| `processyoutube`        | `1`  | Set to 0 to not process youtube embeds |
| `processgmap`           | `1`  | Set to 0 to not process google maps embeds |
| `processvimeo`          | `1`  | Set to 0 to not process vimeo embeds |
| `processotheriframe`    | `1`  | Set to 0 to not process iframes |
| `disallowscripttag`     | `0`  | Set to 1 to not allow script tags. They will be removed completely. |
| `replacescripttag`      | `1`  | Set 0 to not process script tags |
| `disallowskipprocessing`| `0`  | Set to 1 in order to ignore a `<!-- NO_SKIFRAME -->` comment. Editors will not be allowed to skip the filter |
| `youtuberegex`          |      | RegEx used to identify youtube embeds (shows a youtube icon) |
| `vimeoregex`            |      | RegEx used to identify vimeo embeds (shows a vimeo icon)|
| `gmapregex`             |      | RegEx used to identify google maps embeds (shows a google maps icon)|
| `twitterembedregex`     |      | RegEx used to identify twitter script embeds (shows a twitter icon) |

For the default values of the Regular Expressions, please have look into the file `Configuration/TypoScript/constants.typoscript`

### Adapt texts
To adapt the texts, you can override the labels of the translation files in you Template setup section. For the default language

`plugin.tx_skiframe._LOCAL_LANG.default.youtubemessage = <h3>heading of disclamer</h3><p>Your custom text for youtube, including markup. This text is not escaped, so be sure to use valid HTML escape sequences if needed.</p>`

For other languages, replace the "default" part with the language key "e.g. de"

The used keys are showcontent, youtubemessage, vimeomessage, gmapmessage, twittermessage and otheriframemessage (the last one is used for both iframe as well as script tags).