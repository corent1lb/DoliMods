<?php
/* Copyright (C) 2008-2011 Laurent Destailleur  <eldy@users.sourceforge.net>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
 */

/**
 *	    \file       htdocs/concatpdf/admin/concatpdf.php
 *      \ingroup    concatpdf
 *      \brief      Page to setup module ConcatPdf
 *		\version    $Id: concatpdf.php,v 1.3 2011/08/10 10:21:23 eldy Exp $
 */

define('NOCSRFCHECK',1);

$res=0;
if (! $res && file_exists("../main.inc.php")) $res=@include("../main.inc.php");
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");
if (! $res && file_exists("../../../dolibarr/htdocs/main.inc.php")) $res=@include("../../../dolibarr/htdocs/main.inc.php");     // Used on dev env only
if (! $res && file_exists("../../../../dolibarr/htdocs/main.inc.php")) $res=@include("../../../../dolibarr/htdocs/main.inc.php");   // Used on dev env only
if (! $res && file_exists("../../../../../dolibarr/htdocs/main.inc.php")) $res=@include("../../../../../dolibarr/htdocs/main.inc.php");   // Used on dev env only
if (! $res) die("Include of main fails");
require_once(DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php");
require_once(DOL_DOCUMENT_ROOT."/core/lib/files.lib.php");
require_once(DOL_DOCUMENT_ROOT.'/core/class/html.formadmin.class.php');
require_once(DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php');
dol_include_once("/monitoring/lib/monitoring.lib.php");	// We still use old writing to be compatible with old version


if (!$user->admin) accessforbidden();

$langs->load("admin");
$langs->load("other");
$langs->load("concatpdf@concatpdf");

$def = array();
$action=GETPOST("action");
$actionsave=GETPOST("save");

$outputdir=$conf->concatpdf->dir_output.'/invoices';



/*
 * Actions
 */

// None


/**
 * View
 */

$formfile=new FormFile($db);

llxHeader('','ConcatPdf',$linktohelp);

$linkback='<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';
print_fiche_titre($langs->trans("ConcatPdfSetup"),$linkback,'setup');
print '<br>';

clearstatcache();

print $langs->trans("ConcatPDfTakeFileFrom",$outputdir);
print '<br><br>';

print $langs->trans("ConcatPDfPutFileManually");
print '<br><br>';


$listoffiles=dol_dir_list($outputdir,'files');
if (count($listoffiles)) print $formfile->showdocuments('concatpdf','invoices',$outputdir,$_SERVER["PHP_SELF"],0,$user->admin,'',0,0,0,0,0,'',$langs->trans("PathDirectory").' '.$outputdir);
else
{
    print '<div class="titre">'.$langs->trans("PathDirectory").' '.$outputdir.'</div><br>';
    print $langs->trans("NoPDFFileFound");
}


llxFooter();

if (is_object($db)) $db->close();
?>
