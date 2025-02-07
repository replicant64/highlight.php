<?php

/* Copyright (c) 2019 Geert Bergman (geert@scrivo.nl), highlight.php
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution.
 * 3. Neither the name of "highlight.js", "highlight.php", nor the names of its
 *    contributors may be used to endorse or promote products derived from this
 *    software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace HighlightUtilities;

/**
 * A utility class for helper functions that do not exist in the `highlight.js`
 * project.
 *
 * @since 10.0.0
 */
abstract class Functions
{
    /**
     * Get a list of available stylesheets.
     *
     * By default, a list of filenames without the `.css` extension will be returned.
     * This can be configured with the `$filePaths` argument.
     *
     * @api
     *
     * @since 9.15.8.1
     *
     * @param bool $filePaths Return absolute paths to stylesheets instead
     *
     * @return string[]
     */
    public static function getAvailableStyleSheets($filePaths = false)
    {
        $results = array();

        $folder = self::getStyleSheetFolder();
        $dh = @dir($folder);

        if ($dh) {
            while (($entry = $dh->read()) !== false) {
                if (substr($entry, -4, 4) !== ".css") {
                    continue;
                }

                if ($filePaths) {
                    $results[] = implode(DIRECTORY_SEPARATOR, array($folder, $entry));
                } else {
                    $results[] = basename($entry, ".css");
                }
            }

            $dh->close();
        }

        return $results;
    }

    /**
     * Get the RGB representation used for the background of a given theme as an
     * array of three numbers.
     *
     * @api
     *
     * @since 9.18.1.1
     *
     * @param string $name The stylesheet name (with or without the extension)
     *
     * @throws \DomainException when no stylesheet with this name exists
     *
     * @return float[] An array representing RGB numerical values
     */
    public static function getThemeBackgroundColor($name)
    {
        require_once __DIR__ . '/_themeColors.php';

        return _getThemeBackgroundColor(self::getNoCssExtension($name));
    }

    /**
     * Get the contents of the given stylesheet.
     *
     * @api
     *
     * @since 9.15.8.1
     *
     * @param string $name The stylesheet name (with or without the extension)
     *
     * @throws \DomainException when the no stylesheet with this name exists
     *
     * @return false|string The CSS content of the stylesheet or FALSE when
     *                      the stylesheet content could be read
     */
    public static function getStyleSheet($name)
    {
        $path = self::getStyleSheetPath($name);

        return file_get_contents($path);
    }

    /**
     * Get the absolute path to the folder containing the stylesheets distributed in this package.
     *
     * @api
     *
     * @since 9.15.8.1
     *
     * @return string An absolute path to the folder
     */
    public static function getStyleSheetFolder()
    {
        return __DIR__ . '/../Highlight/styles';
    }

    /**
     * Get the directory path for the bundled languages folder.
     *
     * @api
     *
     * @since 9.18.1.4
     *
     * @return string An absolute path to the bundled languages folder
     */
    public static function getLanguagesFolder()
    {
        return __DIR__ . '/../Highlight/languages';
    }

    /**
     * Get the file path for the specified bundled language definition.
     *
     * @api
     *
     * @since 9.18.1.4
     *
     * @param string $name The slug of the language to look for
     *
     * @throws \DomainException when the no definition for this language exists
     *
     * @return string
     */
    public static function getLanguageDefinitionPath($name)
    {
        $path = self::getLanguagesFolder() . '/' . $name . '.json';

        if (!file_exists($path)) {
            throw new \DomainException("There is no language definition for $name");
        }

        return $path;
    }

    /**
     * Get the absolute path to a given stylesheet distributed in this package.
     *
     * @api
     *
     * @since 9.15.8.1
     *
     * @param string $name The stylesheet name (with or without the extension)
     *
     * @throws \DomainException when the no stylesheet with this name exists
     *
     * @return string The absolute path to the stylesheet with the given name
     */
    public static function getStyleSheetPath($name)
    {
        $name = self::getNoCssExtension($name);
        $path = implode(DIRECTORY_SEPARATOR, array(self::getStyleSheetFolder(), $name)) . ".css";

        if (!file_exists($path)) {
            throw new \DomainException("There is no stylesheet with by the name of '$name'.");
        }

        return $path;
    }

    /**
     * Convert the HTML generated by Highlighter and split it up into an array of lines.
     *
     * @api
     *
     * @since 9.15.6.1
     *
     * @param string $html An HTML string generated by `Highlighter::highlight()`
     *
     * @throws \RuntimeException         when the DOM extension is not available
     * @throws \UnexpectedValueException when the given HTML could not be parsed
     *
     * @return string[]|false An array of lines of code as strings. False if an error occurred in splitting up by lines
     */
    public static function splitCodeIntoArray($html)
    {
        if (!extension_loaded("dom")) {
            throw new \RuntimeException("The DOM extension is not loaded but is required.");
        }

        if (trim($html) === "") {
            return array();
        }

        $dom = new \DOMDocument();

        // https://stackoverflow.com/a/8218649
        if (!$dom->loadHTML(mb_convert_encoding($html, "HTML-ENTITIES", "UTF-8"))) {
            throw new \UnexpectedValueException("The given HTML could not be parsed correctly.");
        }

        $xpath = new \DOMXPath($dom);
        $spans = $xpath->query("//span[contains(text(), '\n') or contains(text(), '\r\n')]");

        /** @var \DOMElement $span */
        foreach ($spans as $span) {
            $closingTags = '';
            $openingTags = '';
            $curr = $span;

            while ($curr->tagName === 'span') {
                $closingTags .= '</span>';
                $openingTags = sprintf('<span class="%s">%s', $curr->getAttribute("class"), $openingTags);

                $curr = $curr->parentNode;
            }

            $renderedSpan = $dom->saveHTML($span);
            $finished = preg_replace(
                '/\R/u',
                $closingTags . PHP_EOL . $openingTags,
                $renderedSpan
            );
            $html = str_replace($renderedSpan, $finished, $html);
        }

        return preg_split('/\R/u', $html);
    }

    /**
     * Remove the `.css` extension from a string if it has one.
     *
     * @param string $name
     *
     * @return string
     */
    private static function getNoCssExtension($name)
    {
        if (substr($name, -4, 4) === ".css") {
            $name = preg_replace("/\.css$/", "", $name);
        }

        return $name;
    }
}
