<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

// add flexform to pi1
t3lib_div::loadTCA('tt_content');
$TCA['tt_content']['types']['list']['subtypes_addlist'][$_EXTKEY.'_pi1'] = 'pi_flexform';
$TCA['tt_content']['types']['list']['subtypes_excludelist'][$_EXTKEY.'_pi1'] = 'layout,select_key,pages,recursive';
t3lib_extMgm::addPiFlexFormValue($_EXTKEY .'_pi1', 'FILE:EXT:pmtipafriend/pi1/flexform.xml');

t3lib_extMgm::addPlugin(array('LLL:EXT:pmtipafriend/locallang_db.xml:tt_content.list_type_pi1', $_EXTKEY.'_pi1'),'list_type');


t3lib_extMgm::addStaticFile($_EXTKEY,"pi1/static/","Tip a friend");



if (TYPO3_MODE=="BE")	$TBE_MODULES_EXT["xMOD_db_new_content_el"]["addElClasses"]["tx_pmtipafriend_pi1_wizicon"] = t3lib_extMgm::extPath($_EXTKEY).'pi1/class.tx_pmtipafriend_pi1_wizicon.php';
?>