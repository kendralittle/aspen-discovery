<?php
/**
 * highlight Smarty plugin
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2007.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  Smarty_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_plugin Wiki
 */

/**
 * Smarty plugin
 * -------------------------------------------------------------
 * Type:     modifier
 * Name:     highlight
 * Purpose:  Adds a span tag with class "highlight" around a
 *           specific phrase for highlighting
 * -------------------------------------------------------------
 *
 * @param string $haystack String to highlight
 * @param mixed $needle Array of words to highlight (null for none)
 *
 * @return string          Highlighted, HTML encoded string
 */
function smarty_modifier_highlight($haystack, $needle = null) {
	if ($needle == null) {
		return $haystack;
	}
	// Normalize value to an array so we can loop through it; this saves us from
	// writing the highlighting code twice, once for arrays, once for non-arrays.
	// Also make sure our generated array is empty if needle itself is empty --
	// if $haystack already has highlighting markers in it, we may want to send
	// in a blank needle.
	if (!is_array($needle)) {
		$needle = empty($needle) ? [] : [$needle];
	}

	// Highlight search terms one phrase at a time; we just put in placeholders
	// for the start and end span tags at this point so we can do proper URL
	// encoding later.
	foreach ($needle as $phrase) {
		$phrase = trim(str_replace([
			'"',
			'*',
			'?',
		], '', $phrase));
		if ($phrase != '') {
			$phrase = preg_quote($phrase, '/');
			$haystack = preg_replace("/($phrase)/iu", '{{{{START_HILITE}}}}$1{{{{END_HILITE}}}}', $haystack);
		}
	}

	// URL encode the string, then put in the highlight spans:
	$haystack = str_replace([
			'{{{{START_HILITE}}}}',
			'{{{{END_HILITE}}}}',
		], [
			'<span class="highlight">',
			'</span>',
		], $haystack);

	return $haystack;
}