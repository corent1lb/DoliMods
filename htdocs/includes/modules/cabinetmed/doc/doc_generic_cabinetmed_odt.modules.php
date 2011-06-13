<?php
/* Copyright (C) 2010-2011 Laurent Destailleur <ely@users.sourceforge.net>

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
 * or see http://www.gnu.org/
 */

/**
 *	\file       htdocs/includes/modules/facture/doc/doc_generic_invoice_odt.modules.php
 *	\ingroup    societe
 *	\brief      File of class to build ODT documents for third parties
 *	\author	    Laurent Destailleur
 *	\version    $Id: doc_generic_cabinetmed_odt.modules.php,v 1.2 2011/06/13 15:35:23 eldy Exp $
 */

require_once(DOL_DOCUMENT_ROOT."/includes/modules/facture/modules_facture.php");
require_once(DOL_DOCUMENT_ROOT."/product/class/product.class.php");
require_once(DOL_DOCUMENT_ROOT."/lib/company.lib.php");
require_once(DOL_DOCUMENT_ROOT."/lib/functions2.lib.php");
require_once(DOL_DOCUMENT_ROOT."/lib/files.lib.php");



!!!! NOT USED


/**
 *	\class      doc_generic_cabinetmed_odt
 *	\brief      Class to build documents using ODF templates generator
 */
class doc_generic_cabinetmed_odt extends ModelePDFPatientOutcomes
{
	var $emetteur;	// Objet societe qui emet

	var $phpmin = array(5,2,0);	// Minimum version of PHP required by module
	var $version = 'development';

	/**
	 *		\brief  Constructor
	 *		\param	db		Database handler
	 */
	function doc_generic_cabinetmed_odt($db)
	{
		global $conf,$langs,$mysoc;

		$langs->load("main");
		$langs->load("companies");

		$this->db = $db;
		$this->name = "ODT templates";
		$this->description = $langs->trans("DocumentModelOdt");
		$this->scandir = 'CABINETMED_ADDON_PDF_ODT_PATH';	// Name of constant that is used to save list of directories to scan

		// Dimension page pour format A4
		$this->type = 'odt';
		$this->page_largeur = 0;
		$this->page_hauteur = 0;
		$this->format = array($this->page_largeur,$this->page_hauteur);
		$this->marge_gauche=0;
		$this->marge_droite=0;
		$this->marge_haute=0;
		$this->marge_basse=0;

		$this->option_logo = 1;                    // Affiche logo
		$this->option_tva = 0;                     // Gere option tva FACTURE_TVAOPTION
		$this->option_modereg = 0;                 // Affiche mode reglement
		$this->option_condreg = 0;                 // Affiche conditions reglement
		$this->option_codeproduitservice = 0;      // Affiche code produit-service
		$this->option_multilang = 1;               // Dispo en plusieurs langues
		$this->option_escompte = 0;                // Affiche si il y a eu escompte
		$this->option_credit_note = 0;             // Support credit notes
		$this->option_freetext = 1;				   // Support add of a personalised text
		$this->option_draft_watermark = 1;		   // Support add of a watermark on drafts

		// Recupere emetteur
		$this->emetteur=$mysoc;
		if (! $this->emetteur->pays_code) $this->emetteur->pays_code=substr($langs->defaultlang,-2);    // Par defaut, si n'etait pas defini
	}


    /**
     * Define array with couple substitution key => substitution value
     *
     * @param   $object             Main object to use as data source
     * @param   $outputlangs        Lang object to use for output
     */
    function get_substitutionarray_object($object,$outputlangs)
    {
        global $conf;

        return array(
            'object_id'=>$object->id,
            'object_ref'=>$object->ref,
            'object_ref_customer'=>$object->ref_client,
            'object_ref_supplier'=>$object->ref_fournisseur,
            'object_date'=>dol_print_date($object->date,'day'),
            'object_date_creation'=>dol_print_date($object->date_creation,'dayhour'),
            'object_date_validation'=>dol_print_date($object->date_validation,'dayhour'),
            'object_total_ht'=>price($object->total_ht),
            'object_total_vat'=>price($object->total_tva),
            'object_total_ttc'=>price($object->total_ttc),
            'object_vatrate'=>vatrate($object->tva),
            'object_note_private'=>$object->note,
            'object_note'=>$object->note_public
        );
    }

    /**
     * Define array with couple substitution key => substitution value
     *
     * @param   $line
     * @param   $outputlangs        Lang object to use for output
     */
    function get_substitutionarray_lines($line,$outputlangs)
    {
        global $conf;

        return array(
            'line_fulldesc'=>$line->product_ref.(($line->product_ref && $line->desc)?' - ':'').$line->desc,
            'line_product_ref'=>$line->product_ref,
            'line_desc'=>$line->desc,
            'line_vatrate'=>vatrate($line->tva_tx,true,$line->info_bits),
            'line_up'=>price($line->subprice, 0, $outputlangs),
            'line_qty'=>$line->qty,
            'line_discount_percent'=>($line->remise_percent?$line->remise_percent.'%':''),
            'line_price_ht'=>price($line->total_ht, 0, $outputlangs),
            'line_price_ttc'=>price($line->total_ttc, 0, $outputlangs),
            'line_price_vat'=>price($line->total_tva, 0, $outputlangs),
            'line_date_start'=>$line->date_start,
            'line_date_end'=>$line->date_end
        );
    }

	/**		Return description of a module
     *      @param      langs        Lang object to use for output
	 *      @return     string       Description
	 */
	function info($langs)
	{
		global $conf,$langs;

		$langs->load("companies");
		$langs->load("errors");

		$form = new Form($db);

		$texte = $this->description.".<br>\n";
		$texte.= '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
		$texte.= '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
		$texte.= '<input type="hidden" name="action" value="setModuleOptions">';
		$texte.= '<input type="hidden" name="param1" value="FACTURE_ADDON_PDF_ODT_PATH">';
		$texte.= '<table class="nobordernopadding" width="100%">';

		// List of directories area
		$texte.= '<tr><td>';
		$texttitle=$langs->trans("ListOfDirectories");
		$listofdir=explode(',',preg_replace('/[\r\n]+/',',',trim($conf->global->FACTURE_ADDON_PDF_ODT_PATH)));
		$listoffiles=array();
		foreach($listofdir as $key=>$tmpdir)
		{
			$tmpdir=trim($tmpdir);
			$tmpdir=preg_replace('/DOL_DATA_ROOT/',DOL_DATA_ROOT,$tmpdir);
			if (! $tmpdir) { unset($listofdir[$key]); continue; }
			if (! is_dir($tmpdir)) $texttitle.=img_warning($langs->trans("ErrorDirNotFound",$tmpdir),0);
			else
			{
				$tmpfiles=dol_dir_list($tmpdir,'files',0,'\.odt');
				if (sizeof($tmpfiles)) $listoffiles=array_merge($listoffiles,$tmpfiles);
			}
		}
		$texthelp=$langs->trans("ListOfDirectoriesForModelGenODT");
		// Add list of substitution keys
		$texthelp.='<br>'.$langs->trans("FollowingSubstitutionKeysCanBeUsed").'<br>';
        $dummy=new User($db);
        $tmparray=$this->get_substitutionarray_user($dummy,$langs);
        $nb=0;
        foreach($tmparray as $key => $val)
        {
            $texthelp.='{'.$key.'}<br>';
            $nb++;
            if ($nb >= 5) { $texthelp.='...<br>'; break; }
        }
		$dummy=new Societe($db);
		$tmparray=$this->get_substitutionarray_mysoc($dummy,$langs);
		$nb=0;
		foreach($tmparray as $key => $val)
		{
			$texthelp.='{'.$key.'}<br>';
			$nb++;
			if ($nb >= 5) { $texthelp.='...<br>'; break; }
		}
		$tmparray=$this->get_substitutionarray_thirdparty($dummy,$langs);
		$nb=0;
		foreach($tmparray as $key => $val)
		{
			$texthelp.='{'.$key.'}<br>';
			$nb++;
			if ($nb >= 5) { $texthelp.='...<br>'; break; }
		}
		$texthelp.=$langs->trans("FullListOnOnlineDocumentation");

		$texte.= $form->textwithpicto($texttitle,$texthelp,1,'help');
		//var_dump($listofdir);

		$texte.= '<table><tr><td>';
		$texte.= '<textarea class="flat" cols="60" name="value1">';
		$texte.=$conf->global->FACTURE_ADDON_PDF_ODT_PATH;
		$texte.= '</textarea>';
        $texte.= '</td>';
		$texte.= '<td align="center">&nbsp; ';
        $texte.= '<input type="submit" class="button" value="'.$langs->trans("Modify").'" name="Button">';
        $texte.= '</td>';
		$texte.= '</tr>';
        $texte.= '</table>';

		// Scan directories
		if (sizeof($listofdir)) $texte.=$langs->trans("NumberOfModelFilesFound").': '.sizeof($listoffiles);

		$texte.= '</td>';


		$texte.= '<td valign="top" rowspan="2">';
		$texte.= $langs->trans("ExampleOfDirectoriesForModelGen");
		$texte.= '</td>';
		$texte.= '</tr>';

		/*$texte.= '<tr>';
		$texte.= '<td align="center">';
		$texte.= '<input type="submit" class="button" value="'.$langs->trans("Modify").'" name="Button">';
		$texte.= '</td>';
		$texte.= '</tr>';*/

		$texte.= '</table>';
		$texte.= '</form>';

		return $texte;
	}

	/**
	 *	Function to build a document on disk using the generic odt module.
	 *	@param	    object				Object source to build document
	 *	@param		outputlangs			Lang output object
	 * 	@param		srctemplatepath	    Full path of source filename for generator using a template file
	 *	@return	    int         		1 if OK, <=0 if KO
	 */
	function write_file($object,$outputlangs,$srctemplatepath)
	{
		global $user,$langs,$conf,$mysoc;

		if (empty($srctemplatepath))
		{
			dol_syslog("doc_generic_odt::write_file parameter srctemplatepath empty", LOG_WARNING);
			return -1;
		}

		if (! is_object($outputlangs)) $outputlangs=$langs;
		$sav_charset_output=$outputlangs->charset_output;
		$outputlangs->charset_output='UTF-8';

		$outputlangs->load("main");
		$outputlangs->load("dict");
		$outputlangs->load("companies");
		$outputlangs->load("bills");

		if ($conf->facture->dir_output)
		{
			// If $object is id instead of object
			if (! is_object($object))
			{
				$id = $object;
				$object = new Facture($this->db);
				$object->fetch($id);

				if ($result < 0)
				{
					dol_print_error($db,$object->error);
					return -1;
				}
			}

			$objectref = dol_sanitizeFileName($object->ref);
			$dir = $conf->facture->dir_output;
			if (! preg_match('/specimen/i',$objectref)) $dir.= "/" . $objectref;
			$file = $dir . "/" . $objectref . ".odt";

			if (! file_exists($dir))
			{
				if (create_exdir($dir) < 0)
				{
					$this->error=$langs->transnoentities("ErrorCanNotCreateDir",$dir);
					return -1;
				}
			}

			if (file_exists($dir))
			{
				//print "srctemplatepath=".$srctemplatepath;	// Src filename
				$newfile=basename($srctemplatepath);
				$newfiletmp=preg_replace('/\.odt/i','',$newfile);
				//$file=$dir.'/'.$newfiletmp.'.'.dol_print_date(dol_now(),'%Y%m%d%H%M%S').'.odt';
				$file=$dir.'/'.$newfiletmp.'.odt';
				//print "newdir=".$dir;
				//print "newfile=".$newfile;
				//print "file=".$file;
				//print "conf->societe->dir_temp=".$conf->societe->dir_temp;

				create_exdir($conf->facture->dir_temp);


                // If BILLING contact defined on invoice, we use it
                $usecontact=false;
                $arrayidcontact=$object->getIdContact('external','BILLING');
                if (sizeof($arrayidcontact) > 0)
                {
                    $usecontact=true;
                    $result=$object->fetch_contact($arrayidcontact[0]);
                }

                // Recipient name
                if (! empty($usecontact))
                {
                    // On peut utiliser le nom de la societe du contact
                    if ($conf->global->MAIN_USE_COMPANY_NAME_OF_CONTACT) $socobject = $object->contact;
                    else $socobject = $object->client;
                }
                else
                {
                    $socobject=$object->client;
                }

                // Open and load template
				require_once(DOL_DOCUMENT_ROOT.'/includes/odtphp/odf.php');
				$odfHandler = new odf($srctemplatepath, array(
						'PATH_TO_TMP'	  => $conf->facture->dir_temp,
						'ZIP_PROXY'		  => 'PclZipProxy',	// PhpZipProxy or PclZipProxy. Got "bad compression method" error when using PhpZipProxy.
						'DELIMITER_LEFT'  => '{',
						'DELIMITER_RIGHT' => '}')
				);
				// After construction $odfHandler->contentXml contains content and
				// [!-- BEGIN row.lines --]*[!-- END row.lines --] has been replaced by
				// [!-- BEGIN lines --]*[!-- END lines --]
                //print html_entity_decode($odfHandler->__toString());
                //print exit;

				// Make substitutions
			    $tmparray=$this->get_substitutionarray_user($user,$outputlangs);
                //var_dump($tmparray); exit;
                foreach($tmparray as $key=>$value)
                {
                    try {
                        if (preg_match('/logo$/',$key)) // Image
                        {
                            //var_dump($value);exit;
                            if (file_exists($value)) $odfHandler->setImage($key, $value);
                            else $odfHandler->setVars($key, 'ErrorFileNotFound', true, 'UTF-8');
                        }
                        else    // Text
                        {
                            $odfHandler->setVars($key, $value, true, 'UTF-8');
                        }
                    }
                    catch(OdfException $e)
                    {
                    }
                }
                $tmparray=$this->get_substitutionarray_mysoc($mysoc,$outputlangs);
				//var_dump($tmparray); exit;
				foreach($tmparray as $key=>$value)
				{
					try {
						if (preg_match('/logo$/',$key))	// Image
						{
							//var_dump($value);exit;
							if (file_exists($value)) $odfHandler->setImage($key, $value);
							else $odfHandler->setVars($key, 'ErrorFileNotFound', true, 'UTF-8');
						}
						else	// Text
						{
							$odfHandler->setVars($key, $value, true, 'UTF-8');
						}
					}
					catch(OdfException $e)
					{
					}
				}
				$tmparray=$this->get_substitutionarray_thirdparty($socobject,$outputlangs);
				foreach($tmparray as $key=>$value)
				{
					try {
						if (preg_match('/logo$/',$key))	// Image
						{
							if (file_exists($value)) $odfHandler->setImage($key, $value);
							else $odfHandler->setVars($key, 'ErrorFileNotFound', true, 'UTF-8');
						}
						else	// Text
						{
							$odfHandler->setVars($key, $value, true, 'UTF-8');
						}
					}
					catch(OdfException $e)
					{
					}
				}

			    $tmparray=$this->get_substitutionarray_object($object,$outputlangs);
                foreach($tmparray as $key=>$value)
                {
                    try {
                        if (preg_match('/logo$/',$key)) // Image
                        {
                            if (file_exists($value)) $odfHandler->setImage($key, $value);
                            else $odfHandler->setVars($key, 'ErrorFileNotFound', true, 'UTF-8');
                        }
                        else    // Text
                        {
                            $odfHandler->setVars($key, $value, true, 'UTF-8');
                        }
                    }
                    catch(OdfException $e)
                    {
                    }
                }

                try
                {
                    $listlines = $odfHandler->setSegment('lines');
                    //var_dump($object->lines);exit;
                    foreach ($object->lines as $line)
                    {
                        $tmparray=$this->get_substitutionarray_lines($line,$outputlangs);
                        foreach($tmparray as $key => $val)
                        {
                                try {
                                $listlines->setVars($key, $val);
                             }
                             catch(OdfException $e)
                             {
                             }
                             catch(SegmentException $e)
                             {
                             }
                        }
                        $listlines->merge();
                    }
                    $odfHandler->mergeSegment($listlines);
                }
                catch(OdfException $e)
                {
                    $this->error=$e->getMessage();
                    dol_syslog($this->error, LOG_WARNING);
                    return -1;
                }

                // Write new file
				//$result=$odfHandler->exportAsAttachedFile('toto');
				$odfHandler->saveToDisk($file);

				if (! empty($conf->global->MAIN_UMASK))
				@chmod($file, octdec($conf->global->MAIN_UMASK));

				$odfHandler=null;	// Destroy object

				return 1;   // Success
			}
			else
			{
				$this->error=$langs->transnoentities("ErrorCanNotCreateDir",$dir);
				return -1;
			}
		}

		return -1;
	}

}

?>
