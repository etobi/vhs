<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012 Claus Due <claus@wildside.dk>, Wildside A/S
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 * ************************************************************* */

/**
 * Base class for menu rendering ViewHelpers
 *
 * @author Claus Due <claus@wildside.dk>, Wildside A/S
 * @package Vhs
 * @subpackage ViewHelpers\Page
 */
abstract class Tx_Vhs_ViewHelpers_Page_AbstractMenuViewHelper extends Tx_Fluid_Core_ViewHelper_AbstractTagBasedViewHelper {

	/**
	 * @var string
	 */
	protected $tagName = 'ul';

	/**
	 * @var t3lib_pageSelect
	 */
	protected $pageSelect;

	/**
	 * Initialize
	 */
	public function initializeArguments() {
		$this->registerUniversalTagAttributes();
		$this->registerArgument('tagName', 'string', 'Tag name to use for enclsing container', FALSE, 'ul');
		$this->registerArgument('tagNameChildren', 'string', 'Tag name to use for child nodes surrounding links', FALSE, 'li');
		$this->registerArgument('entryLevel', 'integer', 'Optional entryLevel TS equivalent of the menu', FALSE, 0);
		$this->registerArgument('levels', 'integer', 'Number of levels to render - setting this to a number higher than 1 (one) will expand menu items that are active, to a depth of $levels starting from $entryLevel', FALSE, 1);
		$this->registerArgument('expandAll', 'boolean', 'If TRUE and $levels > 1 then expands all (not just the active) menu items which have submenus', FALSE, FALSE);
		$this->registerArgument('pageUid', 'integer', 'Optional parent page UID to use as top level of menu. If left out will be detected from rootLine using $entryLevel', FALSE, NULL);
		$this->registerArgument('classActive', 'string', 'Optional class name to add to active links', FALSE, 'active');
		$this->registerArgument('classCurrent', 'string', 'Optional class name to add to current link', FALSE, 'current');
		$this->registerArgument('classHasSubpages', 'string', 'Optional class name to add to links which have subpages', FALSE, 'sub');
		$this->registerArgument('useShortcutTarget', 'boolean', 'Optional param for using shortcut target instead of shortcut itself for current link', FALSE, FALSE);
		$this->registerArgument('useShortcutData', 'boolean', 'If TRUE, fetches ALL data from the shortcut target before any additional processing takes place. Note that this overrides everything, including the UID, effectively substituting the shortcut for the target', FALSE, FALSE);
		$this->registerArgument('classFirst', 'string', 'Optional class name for the first menu elment', FALSE, '');
		$this->registerArgument('classLast', 'string', 'Optional class name for the last menu elment', FALSE, '');
		$this->registerArgument('substElementUid', 'boolean', 'Optional parameter for wrapping the link with the uid of the page', FALSE, '');
		$this->registerArgument('includeSpacers', 'boolean', 'Wether or not to include menu spacers in the page select query', FALSE, FALSE);
		$this->registerArgument('bullet', 'string', 'Piece of text/html to insert before each item', FALSE);
		$this->registerArgument('resolveExclude', 'boolean', 'Exclude link if realurl/cooluri flag tx_realurl_exclude is set', FALSE, FALSE);
		$this->registerArgument('showHidden', 'boolean', 'Include "hidden in menu" pages', FALSE, FALSE);
		$this->registerArgument('showCurrent', 'boolean', 'If FALSE, does not display the current page', FALSE, TRUE);
		$this->registerArgument('linkCurrent', 'boolean', 'If FALSE, does not wrap the current page in a link', FALSE, TRUE);
		$this->registerArgument('linkActive', 'boolean', 'If FALSE, does not wrap with links the titles of pages that are active in the rootline', FALSE, TRUE);
		$this->registerArgument('backupVariables', 'array', 'Backup these template variables while rendering the menu and restore them afterwards. Default: [rootLine, page, menu]', FALSE, array('rootLine', 'page', 'menu'));
		$this->registerArgument('titleFields', 'string', 'CSV list of fields to use as link label - default is "nav_title,title", change to for example "tx_myext_somefield,subtitle,nav_title,title". The first field that contains text will be used. Field value resolved AFTER page field overlays.', FALSE, 'nav_title,title');
	}

	/**
	 * Initialize object
	 * @return void
	 */
	public function initializeObject() {
		if (is_array($GLOBALS['TSFE']->fe_user->user) === TRUE) {
			$groups = array(-2, 0);
			$groups = array_merge($groups, (array) array_values($GLOBALS['TSFE']->fe_user->groupData['uid']));
		} else {
			$groups = array(-1, 0);
		}
		$this->pageSelect = new t3lib_pageSelect();
		$this->pageSelect->init((boolean) $this->arguments['showHidden']);
		$clauses = array();
		foreach ($groups as $group) {
			$clause = "fe_group = '" . $group . "' OR fe_group LIKE '" .
				$group . ",%' OR fe_group LIKE '%," . $group . "' OR fe_group LIKE '%," . $group . ",%'";
			array_push($clauses, $clause);
		}
		array_push($clauses, "fe_group = '' OR fe_group = '0'");
		$this->pageSelect->where_groupAccess = ' AND (' . implode(' OR ', $clauses) .  ')';
	}

	/**
	 * @param integer $pageUid
	 * @param array $rootLine
	 * @return boolean
	 */
	protected function isCurrent($pageUid) {
		return $pageUid == $GLOBALS['TSFE']->id;
	}

	/**
	 * @param integer $pageUid
	 * @param array $rootLine
	 * @return boolean
	 */
	protected function isActive($pageUid, $rootLine) {
		foreach ($rootLine as $page) {
			if ($page['uid'] == $pageUid) {
				return TRUE;
			}
		}
		return FALSE;
	}

	/**
	 * Get a list from allowed doktypes for pages
	 *
	 * @return array
	 */
	protected function allowedDoktypeList() {
		$types = array(
			constant('t3lib_pageSelect::DOKTYPE_DEFAULT'),
			constant('t3lib_pageSelect::DOKTYPE_LINK'),
			constant('t3lib_pageSelect::DOKTYPE_SHORTCUT'),
			constant('t3lib_pageSelect::DOKTYPE_MOUNTPOINT')
		);
		if ($this->arguments['includeSpacers']) {
			array_push($types, constant('t3lib_pageSelect::DOKTYPE_SPACER'));
		}
		return $types;
	}

	/**
	 * Get the combined item CSS class based on menu item state and VH arguments
	 *
	 * @param array $pageRow
	 * @return array
	 */
	protected function getItemClass($pageRow) {
		$class = array();
		if ($pageRow['active']) {
			$class[] = $this->arguments['classActive'];
		}
		if ($pageRow['current']) {
			$class[] = $this->arguments['classCurrent'];
		}
		if ($pageRow['hasSubPages']) {
			$class[] = $this->arguments['classHasSubpages'];
		}
		return $class;
	}

	/**
	 * Create the href of a link for page $pageUid
	 *
	 * @param integer $pageUid
	 * @param integer $doktype
	 * @param integer $shortcut
	 * @return string
	 */
	protected function getItemLink($pageUid, $doktype, $shortcut) {
		if ($this->arguments['useShortcutTarget'] && ($doktype == constant('t3lib_pageSelect::DOKTYPE_SHORTCUT') || $doktype == constant('t3lib_pageSelect::DOKTYPE_LINK'))) {
			$pageUid = $shortcut;
		}
		$config = array(
			'parameter' => $pageUid,
			'returnLast' => 'url',
			'additionalParams' => '',
			'useCacheHash' => FALSE
		);
		return $GLOBALS['TSFE']->cObj->typoLink('', $config);
	}

	/**
	 * @param array $page
	 * @param array $rootLine
	 * @return array
	 */
	protected function getMenuItemEntry($page, $rootLine) {
		$getLL = t3lib_div::_GP('L');
		$pageUid = $page['uid'];
		$doktype = $page['doktype'];
		if ($getLL){
			$pageOverlay = $this->pageSelect->getPageOverlay($pageUid, $getLL);
			foreach ($pageOverlay as $name => $value) {
				if (empty($value) === FALSE) {
					$page[$name] = $value;
				}
			}
		} else {
			$page = $this->pageSelect->getPage($pageUid);
		}
		$title = $page['title'];
		$titleFieldList = t3lib_div::trimExplode(',', $this->arguments['titleFields']);
		foreach ($titleFieldList as $titleFieldName) {
			if (empty($page[$titleFieldName]) === FALSE) {
				$title = $page[$titleFieldName];
				break;
			}
		}
		$shortcut = ($doktype == constant('t3lib_pageSelect::DOKTYPE_SHORTCUT')) ? $page['shortcut'] : $page['url'];
		$page['active'] = $this->isActive($pageUid, $rootLine);
		$page['current'] = $this->isCurrent($pageUid);
		$page['hasSubPages'] = (count($this->pageSelect->getMenu($pageUid)) > 0) ? 1 : 0;
		$page['link'] = $this->getItemLink($pageUid, $doktype, $shortcut);
		$page['class'] = implode(' ', $this->getItemClass($page));
		$page['title'] = $title;
		$page['doktype'] = $doktype;
		return $page;
	}

	/**
	 * Filter the fetched menu according to visibility etc.
	 *
	 * @param array $menu
	 * @param array $rootLine
	 * @return array
	 */
	protected function parseMenu($menu, $rootLine) {
		$classFirst = $this->arguments['classFirst'];
		$classLast = $this->arguments['classLast'];
		$filtered = array();
		$allowedDocumentTypes = $this->allowedDoktypeList();
		foreach ($menu as $page) {
			if ($page['hidden'] == 1) {
				continue;
			} elseif ($page['nav_hide'] == 1 && $this->arguments['showHidden'] < 1) {
				continue;
			} elseif ($page['l18n_cfg'] == 1 && $GLOBALS['TSFE']->sys_language_uid == 0) {
				continue;
			} elseif ($page['l18n_cfg'] == 2 && $GLOBALS['TSFE']->sys_language_uid != 0) {
				continue;
			} elseif (in_array($page['doktype'], $allowedDocumentTypes)) {
				$page = $this->getMenuItemEntry($page, $rootLine);
				$filtered[] = $page;
			}
		}
		if (isset($filtered[0])) {
				// at least 1 page in menu
			if ($classFirst) {
				$filtered[0]['class'] = trim($filtered[0]['class'] . ' ' . $classFirst);
			}
			if ($classLast) {
				$length = count($filtered);
				$filtered[$length - 1]['class'] = trim($filtered[$length - 1]['class'] . ' ' . $classLast);
			}
		}
		return $filtered;
	}

	/**
	 * Automatically render a menu
	 *
	 * @param array $menu
	 * @param integer $level
	 * @return string
	 */
	protected function autoRender($menu, $level = 1) {
		$tagName = $this->arguments['tagNameChildren'];
		$substElementUid = $this->arguments['substElementUid'];
		$linkCurrent = (boolean) $this->arguments['linkCurrent'];
		$linkActive = (boolean) $this->arguments['linkActive'];
		$showCurrent = (boolean) $this->arguments['showCurrent'];
		$html = array();
		foreach ($menu as $page) {
			if ($page['current'] && !$showCurrent) {
				continue;
			}
			$class = trim($page['class']) != '' ? ' class="' . $page['class'] . '"' : '';
			$elementId = $substElementUid ? ' id="elem_' . $page['uid'] . '"' : '';
			$target = $page['target'] != '' ? ' target="' . $page['target'] . '"' : '';
			$html[] = '<' . $tagName . $elementId . $class . '>';
			if ($page['current'] && $linkCurrent === FALSE) {
				$html[] = $page['title'];
			} elseif ($page['active'] && $linkActive === FALSE) {
				$html[] = $page['title'];
			} else {
				$html[] = '<a href="' . $page['link'] . '"' . $class . $target . '>' . $page['title'] . '</a>';
			}

			if (($page['active'] || $this->arguments['expandAll']) && $page['hasSubPages'] && $level < $this->arguments['levels']) {
				$rootLine = $this->pageSelect->getRootLine($page['uid']);
				$rootLine = $this->parseMenu($rootLine, $rootLine);
				$subMenu = $this->pageSelect->getMenu($page['uid']);
				$subMenu = $this->parseMenu($subMenu, $rootLine);
				$renderedSubMenu = $this->autoRender($subMenu, $level + 1);
				$this->tag->setTagName($this->arguments['tagName']);
				$this->tag->setContent($renderedSubMenu);
				$this->tag->addAttribute('class', ($this->arguments['class'] ? $this->arguments['class'] . ' lvl-' : 'lvl-') . strval($level));
				$html[] = $this->tag->render();
				$this->tag->addAttribute('class', $this->arguments['class']);
			}
			$html[] = '</' . $tagName . '>';
		}
		return implode(LF, $html);
	}

}
