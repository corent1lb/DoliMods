<?php
/* Copyright (C) 2004 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2005 Simon TOSSER <simon@kornog-computing.com>
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
 *
 */

/**
 *      \file       htdocs/compta/ventilation/fournisseur/fiche.php
 *      \ingroup    compta
 *      \brief      Page fiche ventilation
 */

// Dolibarr environment
$res=@include("../main.inc.php");
if (! $res && file_exists("../main.inc.php")) $res=@include("../main.inc.php");
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");
if (! $res) die("Include of main fails");

require_once(DOL_DOCUMENT_ROOT."/fourn/class/fournisseur.facture.class.php");

$langs->load("bills");
$langs->load("products");
$langs->load("ventilation@ventilation");

$mesg = '';

if (!$user->rights->compta->ventilation->creer) accessforbidden();

if ($_POST["action"] == 'ventil' && $user->rights->compta->ventilation->creer)
{
  $sql = " UPDATE ".MAIN_DB_PREFIX."facture_fourn_det";
  $sql .= " SET fk_code_ventilation = ".$_POST["codeventil"];
  $sql .= " WHERE rowid = ".$_GET["id"];

  $db->query($sql);
}

llxHeader("","","Fiche ventilation");

if ($cancel == $langs->trans("Cancel"))
{
  $action = '';
}
/*
 *
 *
 */

$sql = "SELECT rowid, numero, intitule";
$sql .= " FROM ".MAIN_DB_PREFIX."compta_compte_generaux";
$sql .= " ORDER BY numero ASC";

$cgs = array();
$cgn = array();
$result = $db->query($sql);
if ($result)
{
  $num = $db->num_rows($result);
  $i = 0; 
  
  while ($i < $num)
    {
      $row = $db->fetch_row($result);
      $cgs[$row[0]] = $row[1] . ' ' . $row[2];
      $i++;
    }
}

/*
 * Creation
 *
 */
$form = new Form($db);
$facturefournisseur_static=new FactureFournisseur($db);

if($_GET["id"])
{
  $sql = "SELECT f.facnumber, f.rowid as facid, l.fk_product, l.description, l.rowid, l.fk_code_ventilation, ";
  $sql.= " p.rowid as product_id, p.ref as product_ref, p.label as product_label";
  $sql .= " FROM ".MAIN_DB_PREFIX."facture_fourn_det as l";
  $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."product as p ON p.rowid = l.fk_product";
  $sql .= " , ".MAIN_DB_PREFIX."facture_fourn as f";
  $sql .= " WHERE f.rowid = l.fk_facture_fourn AND f.fk_statut > 0 AND l.rowid = ".$_GET["id"];
   
  $result = $db->query($sql);

  if ($result)
    {
      $num_lignes = $db->num_rows($result);
      $i = 0; 
      
      if ($num_lignes)
	{
	  
	  $objp = $db->fetch_object($result);


	  
	      print '<form action="fiche.php?id='.$_GET["id"].'" method="post">'."\n";
	      print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	      print '<input type="hidden" name="action" value="ventil">';
	   

	  
	  print_titre("Ventilation");
	  
	  print '<table class="border" width="100%" cellspacing="0" cellpadding="4">';
	  
	  // ref invoice
	  
	  print '<tr><td>'.$langs->trans("BillsSuppliers").'</td>';
	  $facturefournisseur_static->ref=$objp->facnumber;
		$facturefournisseur_static->id=$objp->facid;
		print '<td>'.$facturefournisseur_static->getNomUrl(1).'</td>';
    print '</tr>';
	  
      

	  print '<tr><td width="20%">Ligne</td>';
	  print '<td>'.stripslashes(nl2br($objp->description)).'</td></tr>';
	  print '<tr><td width="20%">'.$langs->trans("ProductLabel").'</td>';
	  print '<td>'.dol_trunc($objp->product_label,24).'</td>';
	  print '<tr><td width="20%">'.$langs->trans("Account").'</td><td>';
    print $cgs[$objp->fk_code_ventilation];
    print '<tr><td width="20%">'.$langs->trans("NewAccount").'</td><td>';
	  print $form->selectarray("codeventil",$cgs, $objp->fk_code_ventilation);
	  print '</td></tr>';
	  print '<tr><td>&nbsp;</td><td><input type="submit" value="'.$langs->trans("update").'"></td></tr>';
	    
	  print '</table>';
	  print '</form>';
	}
      else
	{
	  print "Error";
	}
    }
  else
    {
      print "Error";
    }
}
else
{
  print "Error ID incorrect";
}

$db->close();

llxFooter();
?>
