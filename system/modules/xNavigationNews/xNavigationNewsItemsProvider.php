<?php if (!defined('TL_ROOT')) die('You can not access this file directly!');

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2010 Leo Feyer
 *
 * Formerly known as TYPOlight Open Source CMS.
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 * @copyright  InfinitySoft 2010
 * @author     Tristan Lins <tristan.lins@infinitysoft.de>
 * @package    xNavigation News
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 */


/**
 * Class xNavigationNewsItemsProvider
 * 
 * xNavigation provider to generate news items.
 * @copyright  InfinitySoft 2010
 * @author     Tristan Lins <tristan.lins@infinitysoft.de>
 * @package    xNavigation News
 */
class xNavigationNewsItemsProvider extends xNavigationProvider
{
	public function generateItems(ModuleXNavigation &$objXNavigation,
		Database_Result $objCurrentPage,
		$blnCurrentPageActive,
		$blnCurrentPageTrail,
		&$arrItems,
		$intLevel,
		$intMaxLevel,
		$intHardLevel) 
	{
		if ($objCurrentPage->xnav_include_news_items)
		{
			// Get news navigation
			if (	$this instanceof ModuleXSitemap
				||  $objCurrentPage->xnav_news_items_visibility == 'map_always'
				||	$objCurrentPage->xnav_news_items_visibility == 'map_default'
				&&	($blnCurrentPageActive || $blnCurrentPageTrail)
				&&	($intHardLevel == 0 || $intHardLevel > 0 &&  $intLevel<=$intHardLevel))
			{
				$this->import('Database');
		
				$arrNewsArchives = unserialize($objCurrentPage->xnav_news_archives);
				$time = time();
				$objNews = $this->Database->prepare("
						SELECT
							*
						FROM
							tl_news
						WHERE
							pid IN (" . implode(array_map('intval', $arrNewsArchives)) . ")
						AND	published=1
						AND	(	(start='' || start>$time)
							OR	(stop='' || stop<$time))");
				if ($objCurrentPage->xnav_news_items_limit > 0)
				{
					$objNews->limit($objCurrentPage->xnav_news_items_limit);
				}
				$objNews = $objNews->execute();
				
				while ($objNews->next())
				{
					$objNewsArchive = $this->Database->prepare("
							SELECT
								*
							FROM
								tl_news_archive
							WHERE
								id=?")
						->execute($objNews->pid);
					if ($objNewsArchive->next())
					{
						$arrJumpTo = $GLOBALS['objPage']->row();
						if ($objNewsArchive->jumpTo > 0)
						{
							$objJumpTo = $this->Database->prepare("
									SELECT
										*
									FROM
										tl_page
									WHERE
										id=?")
								->execute($objNewsArchive->jumpTo);
							if ($objJumpTo->next())
							{
								$arrJumpTo = $objJumpTo->row();
							}
						}
						
						$row = $objNews->row();
						$row['isActive'] = ($this->Input->get('items') == $objNews->id || $this->Input->get('items') == $objNews->alias);
						$row['subitems'] = '';
						$row['class'] = '';
						$row['title'] = specialchars($objNews->headline);
						$row['link'] = $objNews->headline;
						$row['href'] = $this->generateFrontendUrl($arrJumpTo, '/items/' . (strlen($objNews->alias) ? $objNews->alias : $objNews->id));
						$row['itemtype'] = 'news';
						
						$arrItems[] = $row;
					}
				}
			}
		}
	}
	
	/**
	 * Generate the news items.
	 * 
	 * @param Database_Result $objCurrentPage
	 * @param array $objNewsArchives
	 * @param array $arrItems
	 * @param integer $time
	 */
	protected function generateNewsItems(Database_Result &$objCurrentPage, &$arrItems) {
		/*
		$arrData = array();
		$maxQuantity = 0;
		switch ($objCurrentPage->xNavigationNewsArchiveFormat) {
		case 'news_year':
			$format = 'Y';
			$param = 'year';
			break;
		case 'news_month':
		default:
			$format = 'Ym';
			$param = 'month';
		}

		$jumpTo = $objCurrentPage->row();
		if ($objCurrentPage->xNavigationNewsArchiveJumpTo > 0) {
			$objJumpTo = $this->Database->prepare("SELECT * FROM tl_page WHERE id = ?")
										->execute($objCurrentPage->xNavigationNewsArchiveJumpTo);
			if ($objJumpTo->next())
				$jumpTo = $objJumpTo->row();
		}
		
		foreach ($objNewsArchives as $id)
		{
			// Get all active items
			$objArchives = $this->Database->prepare("SELECT date FROM tl_news WHERE pid=?" . (!BE_USER_LOGGED_IN ? " AND (start='' OR start<$time) AND (stop='' OR stop>$time) AND published=1" : "") . " ORDER BY date DESC")
										  ->execute($id);

			while ($objArchives->next())
			{
				++$arrData[date($format, $objArchives->date)];
				if ($arrData[date($format, $objArchives->date)] > $maxQuantity) {
					$maxQuantity = $arrData[date($format, $objArchives->date)];
				}
			}
		}
		krsort($arrData);
		
		$url = $this->generateFrontendUrl($jumpTo, sprintf('/%s/%%s', $param));
		
		if (count($arrData)) {
			$n = count($arrItems);
			foreach ($arrData as $intDate => $intCount) {
				$quantity = sprintf((($intCount < 2) ? $GLOBALS['TL_LANG']['MSC']['entry'] : $GLOBALS['TL_LANG']['MSC']['entries']), $intCount);
				switch ($objCurrentPage->xNavigationNewsArchiveFormat) {
				case 'news_year':
					$intYear = $intDate;
					$intMonth = '0';
					$link = $title = specialchars($intYear . ($objCurrentPage->xNavigationNewsArchiveShowQuantity=='1' ? ' (' . $quantity . ')' : ''));
					break;
				case 'news_month':
				default:
					$intYear = intval(substr($intDate, 0, 4));
					$intMonth = intval(substr($intDate, 4));
					$link = $title = specialchars($GLOBALS['TL_LANG']['MONTHS'][$intMonth-1].' '.$intYear . ($objCurrentPage->xNavigationNewsArchiveShowQuantity=='1' ? ' (' . $quantity . ')' : ''));
				}
				
				$arrItems[] = array(
					'date' => $intDate,
					'link' => $link,
					'href' => sprintf($url, $intDate),
					'title' => $title,
					'isActive' => ($this->Input->get($param) == $intDate),
					'quantity' => $quantity,
					'maxQuantity' => $maxQuantity,
					'itemtype' => 'news_archive',
					'class' => ''
				);
			}
			
			$last = count($arrItems) - 1;
			
			$arrItems[$n]['class'] = trim($arrItems[$n]['class'] . ' first_news_archive');
			$arrItems[$last]['class'] = trim($arrItems[$last]['class'] . ' last_news_archive');
		}
		*/
	}
}
?>