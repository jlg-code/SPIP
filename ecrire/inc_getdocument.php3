<?php

/***************************************************************************\
 *  SPIP, Systeme de publication pour l'internet                           *
 *                                                                         *
 *  Copyright (c) 2001-2005                                                *
 *  Arnaud Martin, Antoine Pitrou, Philippe Riviere, Emmanuel Saint-James  *
 *                                                                         *
 *  Ce programme est un logiciel libre distribue sous licence GNU/GPL.     *
 *  Pour plus de details voir le fichier COPYING.txt ou l'aide en ligne.   *
\***************************************************************************/


//
// Fonctions de spip_image.php3

//
// Ce fichier ne sera execute qu'une fois
if (defined("_ECRIRE_INC_GETDOCUMENT")) return;
define("_ECRIRE_INC_GETDOCUMENT", "1");

if (!function_exists('file_get_contents'))
  eval('function file_get_contents($f) {return join("",file($f));}');


// Creer IMG/pdf/
function creer_repertoire_documents($ext) {
	$rep = _DIR_DOC . creer_repertoire(_DIR_DOC, $ext);

	if (!$ext OR !$rep) {
		spip_log("creer_repertoire_documents interdit");
		exit;
	}

	if (lire_meta("creer_htaccess") == 'oui') {
		include_ecrire('inc_acces.php3');
		verifier_htaccess($rep);
	}

	return $rep;
}

// Efface le repertoire de maniere recursive !
function effacer_repertoire_temporaire($nom) {
	$d = opendir($nom);
	while ($f = readdir($d)) {
		if (is_file("$nom/$f"))
			@unlink("$nom/$f");
		else if ($f <> '.' AND $f <> '..'
		AND is_dir("$nom/$f"))
			effacer_repertoire_temporaire("$nom/$f");
	}
	@rmdir($nom);
}

function copier_document($ext, $orig, $source) {

	$dir = creer_repertoire_documents($ext);
	$dest = $dir .
		ereg_replace("[^.a-zA-Z0-9_=-]+", "_", 
			translitteration(ereg_replace("\.([^.]+)$", "", 
						      ereg_replace("<[^>]*>", '', basename($orig)))));

	// Si le document "source" est deja au bon endroit, ne rien faire
	if ($source == ($dest . '.' . $ext))
		return $source;

	// sinon tourner jusqu'a trouver un numero correct
	$n = 0;
	while (@file_exists($newFile = $dest.($n++ ? '-'.$n : '').'.'.$ext));

	$newFile = preg_replace('/[.]+/', '.', $newFile);

	if ($r = deplacer_fichier_upload($source, $newFile))
		return $newFile;
}

//
// Deplacer un fichier
//

function deplacer_fichier_upload($source, $dest) {
	// Securite
	if (strstr($dest, "..")) {
		spip_log("stop deplacer_fichier_upload: '$dest'");
		exit;
	}

	$ok = @copy($source, $dest);
	if (!$ok) $ok = @move_uploaded_file($source, $dest);
	if ($ok)
		@chmod($dest, 0666);
	else {
		$f = @fopen($dest,'w');
		if ($f) {
			fclose ($f);
		} else {
			redirige_par_entete("spip_test_dirs.php3?test_dir=".
				dirname($dest));
		}
		@unlink($dest);
	}
	return $ok;
}


// Erreurs d'upload
// renvoie false si pas d'erreur
// et true si erreur = pas de fichier
// pour les autres erreurs affiche le message d'erreur et meurt
function check_upload_error($error, $msg='') {
	switch ($error) {
		case 0:
			return false;
		case 4: /* UPLOAD_ERR_NO_FILE */
			return true;

		# on peut affiner les differents messages d'erreur
		case 1: /* UPLOAD_ERR_INI_SIZE */
			$msg = _T('upload_limit',
			array('max' => ini_get('upload_max_filesize')));
			break;
		case 2: /* UPLOAD_ERR_FORM_SIZE */
			$msg = _T('upload_limit',
			array('max' => ini_get('upload_max_filesize')));
			break;
		case 3: /* UPLOAD_ERR_PARTIAL  */
			$msg = _T('upload_limit',
			array('max' => ini_get('upload_max_filesize')));
			break;
	}

	spip_log ("erreur upload $error");

	include_ecrire('inc_presentation.php3');
	install_debut_html(_T('forum_titre_erreur'));
	echo "<p>$msg</p>\n";

	install_fin_html(_DIR_RESTREINT_ABS . $GLOBALS['redirect']);
	exit;
}


//
// Gestion des fichiers ZIP
//
function accepte_fichier_upload ($f) {
	if (!ereg(".*__MACOSX/", $f)
	AND !ereg("^\.", basename($f))) {
		$ext = substr(strrchr($f, "."), 1);
		$result = spip_query("SELECT * FROM spip_types_documents
		WHERE extension='"
		. corriger_extension(addslashes(strtolower($ext)))
		. "' AND upload='oui'");
		if ($row = @spip_fetch_array($result))
			return true;
	}
}

# callback pour le deballage dans spip_image.php3
# http://www.phpconcept.net/pclzip/man/en/?options-pclzip_cb_pre_extractfunction
function callback_deballe_fichier($p_event, &$p_header) {
	if (accepte_fichier_upload($p_header['filename'])) {
		$p_header['filename'] = _tmp_dir . basename($p_header['filename']);
		return 1;
	} else {
		return 0;
	}
}

function verifier_compactes($zip) {
	if ($list = $zip->listContent()) {
	// si pas possible de decompacter: installer comme fichier zip joint
	// Verifier si le contenu peut etre uploade (verif extension)
		$aff_fichiers = array();
		foreach ($list as $file) {
			if (accepte_fichier_upload($f = $file['stored_filename']))
				$aff_fichiers[]= $f;
			else
				spip_log("chargement de $f interdit");
		}
		sort($aff_fichiers);
		return $aff_fichiers;
	}
}

// Si on doit conserver une copie locale des fichiers distants, autant que ca
// soit a un endroit canonique -- si ca peut etre bijectif c'est encore mieux,
// mais la tout de suite je ne trouve pas l'idee, etant donne les limitations
// des filesystems
function nom_fichier_copie_locale($source, $extension) {
	$dir = _DIR_IMG. creer_repertoire(_DIR_IMG, 'distant'); # IMG/distant/
	$dir2 = $dir . creer_repertoire($dir, $extension); 		# IMG/distant/pdf/
	return $dir2 . substr(basename($source).'-'.md5($source),0,12).
		substr(md5($source),0,4).'.'.$extension;
}

//
// Donne le nom de la copie locale de la source
//
function fichier_copie_locale($source) {
	// Si c'est une image de IMG/ pas de souci
	if (preg_match(',^'._DIR_IMG.',', $source))
		return $source;

	// Si l'extension n'est pas precisee, aller la chercher dans la table
	// des documents -- si la source n'est pas dans la table des documents,
	// on ne fait rien
	if ($t = spip_fetch_array(spip_query("SELECT * FROM spip_documents
	WHERE fichier='".addslashes($source)."' AND distant='oui'")))
		list($extension) = spip_fetch_array(spip_query("SELECT extension
		FROM spip_types_documents WHERE id_type=".$t['id_type']));

	if ($extension)
		return nom_fichier_copie_locale($source, $extension);
}


// Recuperer les infos d'un document distant, sans trop le telecharger
function recuperer_infos_distantes($source, $max=0) {
	include_ecrire('inc_sites.php3');

	$a = array();

	// On va directement charger le debut des images et des fichiers html,
	// de maniere a attrapper le maximum d'infos (titre, taille, etc). Si
	// ca echoue l'utilisateur devra les entrer...
	if ($headers = recuperer_page($source, false, true, $max)) {
		list($headers, $a['body']) = split("\n\n", $headers, 2);
		if (preg_match(",\nContent-Type: *([^[:space:];]*),i",
			"\n$headers", $regs)
		AND $mime_type = addslashes(trim($regs[1]))
		AND $s = spip_query("SELECT id_type,extension FROM spip_types_documents
			WHERE mime_type='$mime_type'")
		AND $t = spip_fetch_array($s)) {
			spip_log("mime-type $mime_type ok");
			$a['id_type'] = $t['id_type'];
			$a['extension'] = $t['extension'];
		} else {
			# par defaut on retombe sur '.bin' si c'est autorise
			spip_log("mime-type $mime_type inconnu");
			$t = spip_fetch_array(spip_query(
				"SELECT id_type,extension FROM spip_types_documents
				WHERE extension='bin'"));
			if (!$t) return false;
			$a['id_type'] = $t['id_type'];
			$a['extension'] = $t['extension'];
		}

		if (preg_match(",\nContent-Length: *([^[:space:]]*),i",
			"\n$headers", $regs))
			$a['taille'] = intval($regs[1]);
	}

	// Echec avec HEAD, on tente avec GET
	if (!$a AND !$max) {
	spip_log("tente $source");
		$a = recuperer_infos_distantes($source, 1024*1024);
	}

	// S'il s'agit d'une image pas trop grosse ou d'un fichier html, on va aller
	// recharger le document en GET et recuperer des donnees supplementaires...
	if (preg_match(',^image/(jpeg|gif|png|swf),', $mime_type)) {
		if ($max == 0
		    AND $a['taille'] < 1024*1024
		AND ereg(",".$a['extension'].",",
		','.lire_meta('formats_graphiques').',')){
			$a = recuperer_infos_distantes($source, 1024*1024);
		}
		else if ($a['body']) {
			$a['fichier'] = nom_fichier_copie_locale($source, $a['extension']);
			ecrire_fichier($a['fichier'], $a['body']);
			$size_image = @getimagesize($a['fichier']);
			$a['largeur'] = intval($size_image[0]);
			$a['hauteur'] = intval($size_image[1]);
			$a['type_image'] = true;
		}
	}
	
	if ($mime_type == 'text/html') {
		$page = recuperer_page($source, true, false, 1024*1024);
		if(preg_match(',<title>(.*?)</title>,ims', $page, $regs))
			$a['titre'] = corriger_caracteres(trim($regs[1]));
			if (!$a['taille']) $a['taille'] = strlen($page); # a peu pres
	}

	return $a;
}


//
// Ajouter un document (au format $_FILES)
//
# $source,	# le fichier sur le serveur (/var/tmp/xyz34)
# $nom_envoye,	# son nom chez le client (portequoi.pdf)
# $type_lien,	# lie a un article, une breve ou une rubrique ?
# $id_lien,	# identifiant de l'article (ou rubrique) lie
# $mode,	# 'vignette' => image en mode image
#		# ou vignette personnalisee liee a un document
		# 'document' => doc ou image en mode document
		# 'distant' => lien internet
# $id_document,	# pour une vignette, l'id_document de maman
# $actifs	# les documents dont il faudra ouvrir la boite de dialogue

function ajouter_un_document ($source, $nom_envoye, $type_lien, $id_lien, $mode, $id_document, &$documents_actifs) {

// Documents distants : pas trop de verifications bloquantes, mais un test
// via une requete HEAD pour savoir si la ressource existe (non 404), si le
// content-type est connu, et si possible recuperer la taille, voire plus.
	spip_log ("ajout du document $nom_envoye  ($mode $type_lien $id_lien $id_document)");
	if ($mode == 'distant') {
		if ($a = recuperer_infos_distantes($source)) {
			# fichier local pour creer la vignette (!!),
			# on retablira la valeur de l'url a la fin
			$fichier = $a['fichier'];

			$id_type = $a['id_type'];
			$taille = $a['taille'];
			$titre = $a['titre'];
			$largeur = $a['largeur'];
			$hauteur = $a['hauteur'];
			$ext = $a['extension'];
			$type_image = $a['type_image'];

			$distant = 'oui';
			$mode = 'document';
		}
		else {
			spip_log("Echec du lien vers le document $source, abandon");
			return;
		}
	}

	else {
	
		$distant = 'non';

		// tester le type de document :
		// - interdit a l'upload ?
		// - quel numero dans spip_types_documents ?  =-(
		// - est-ce "inclus" comme une image ?
		ereg("\.([^.]+)$", $nom_envoye, $match);
		$ext = addslashes(corriger_extension(strtolower($match[1])));

		// Si le fichier est de type inconnu, on va le stocker en .zip
		if (!$row = spip_fetch_array(spip_query(
		"SELECT * FROM spip_types_documents
		WHERE extension='$ext' AND upload='oui'"))) {

/* STOCKER LES DOCUMENTS INCONNUS AU FORMAT .BIN */
/*			$ext = 'bin';
			$nom_envoye .= '.bin';
			spip_log("Extension $ext");
			if (!$row = spip_fetch_array(spip_query(
			"SELECT * FROM spip_types_documents
			WHERE extension='bin' AND upload='oui'"))) {
				spip_log("Extension $ext interdite a l'upload");
				return;
			}
*/

/* STOCKER LES DOCUMENTS INCONNUS AU FORMAT .ZIP */
			$ext = 'zip';

			if (!$row = spip_fetch_array(spip_query(
			"SELECT * FROM spip_types_documents
			WHERE extension='zip' AND upload='oui'"))) {
				spip_log("Extension $ext interdite a l'upload");
				return;
			}
			if (!$tmp_dir = tempnam(_DIR_SESSIONS, 'tmp_upload')) return;
			@unlink($tmp_dir); @mkdir($tmp_dir);
			if (!is_dir(_DIR_IMG.'tmp')) @mkdir(_DIR_IMG.'tmp');
			$tmp = $tmp_dir.'/'.translitteration($nom_envoye);
			$nom_envoye .= '.zip'; # conserver l'extension dans le nom de fichier, par exemple toto.js => toto.js.zip
			$fichier = deplacer_fichier_upload($source, $tmp);
			require_once(_DIR_RESTREINT . 'pclzip.lib.php');
			$source = _DIR_IMG.'tmp/archive.zip';
			$archive = new PclZip($source);
			$v_list = $archive->create($tmp,
				PCLZIP_OPT_REMOVE_PATH, $tmp_dir,
				PCLZIP_OPT_ADD_PATH, '');
			effacer_repertoire_temporaire($tmp_dir);
			if (!$v_list) {
				spip_log("Echec creation du zip ");
				return;
			}
		}
		$id_type = $row['id_type'];	# numero du type dans spip_types_documents:(
		$type_inclus_image = ($row['inclus'] == 'image');

		// Recopier le fichier a son emplacement definitif
		$fichier = copier_document($ext, $nom_envoye, $source);
		if (!$fichier) {
			spip_log("Impossible de copier_document($ext, $nom_envoye, $source)");
			return;
		}

		// Quelques infos sur le fichier
		if (!@file_exists($fichier)
		OR !$taille = @filesize($fichier)) {
			spip_log ("Echec copie du fichier $fichier");
			return;
		}

		// Si c'est une image, recuperer sa taille et son type (detecte aussi swf)
		$size_image = @getimagesize($fichier);
		$largeur = intval($size_image[0]);
		$hauteur = intval($size_image[1]);
		$type_image = decoder_type_image($size_image[2]);

		// Si on veut uploader une vignette, il faut qu'elle ait ete bien lue
		if ($mode == 'vignette' AND !($largeur * $hauteur)) {
			@unlink($fichier);
			return;
		}
	}

	// regler l'ancre du retour
	if (!$GLOBALS['ancre']) {
		if ($mode=='vignette')
			$GLOBALS['ancre'] = 'images';
		else if ($type_image)
			$GLOBALS['ancre'] = 'portfolio';
		else
			$GLOBALS['ancre'] = 'documents';
	}

	// Preparation vignette du document $id_document
	$id_document=intval($id_document);
	if ($mode == 'vignette' AND $id_document_lie = $id_document) {
		# on force le statut "document" de ce fichier (inutile ?)
		spip_query("UPDATE spip_documents
			SET mode='document'
			WHERE id_document=$id_document");
		$id_document = 0;
	}

	// Installer le document dans la base
	// attention piege semantique : les images s'installent en mode 'vignette'
	// note : la fonction peut "mettre a jour un document" si on lui
	// passe "mode=document" et "id_document=.." (pas utilise)
	if (!$id_document) {
		// Inserer le nouveau doc et recuperer son id_
		$id_document = spip_abstract_insert("spip_documents",
		"(id_type, titre, date, distant)",
		"($id_type, '".texte_script($titre)."', NOW(), '$distant')");

		if ($id_lien
		AND preg_match('/^[a-z0-9_]+$/i', $type_lien) # securite
		)
			spip_query("INSERT INTO spip_documents_".$type_lien."s
				(id_document, id_".$type_lien.")
				VALUES ($id_document, $id_lien)");

		// par defaut (upload ZIP ou ftp) integrer
		// les images en mode 'vignette' et le reste en mode document
		if (!$mode)
			if ($type_image AND $type_inclus_image)
				$mode = 'vignette';
			else
				$mode = 'document';
		$update = "mode='$mode', ";
	}

	// Mise a jour des donnees
	spip_query("UPDATE spip_documents
		SET $update
		taille='$taille', largeur='$largeur', hauteur='$hauteur',
		fichier='$fichier'
		WHERE id_document=$id_document");

	if ($id_document_lie) {
		spip_query ("UPDATE spip_documents
		SET id_vignette=$id_document
		WHERE id_document=$id_document_lie");
		// hack pour que le retour vers ecrire/ active le bon doc.
		$documents_actifs[] = $id_document_lie; 
	}
	else
		$documents_actifs[] = $id_document; 

/**
	DESACTIVE CAR UTILISATION PAR DEFAUT DES IMAGES REDUITES

	// Creer la vignette des images
	if (ereg(",$ext,", ','.lire_meta('formats_graphiques').',')
	AND $mode == 'document'
	AND $type_image)
		creer_fichier_vignette($fichier);

**/

	// Pour les fichiers distants remettre l'URL de base
	if ($distant == 'oui')
		spip_query("UPDATE spip_documents SET fichier='".addslashes($source)."'
		WHERE id_document = $id_document");

	// Demander l'indexation du document
	include_ecrire('inc_index.php3');
	marquer_indexer('document', $id_document);

	return true;
}

function afficher_compactes($fichiers, $args, $action) {
// presenter une interface pour choisir si fichier joint ou decompacte
// passer ca en squelette un de ces jours.

	include_ecrire ("inc_presentation.php3");
	install_debut_html(_T('upload_fichier_zip'));
	echo "<p>",
		_T('upload_fichier_zip_texte'),
		"</p>",
		"<p>",
		_T('upload_fichier_zip_texte2'),
		"</p>",
		construire_upload(
			"<div><input type='radio' checked='checked' name='sousaction5' value='5'>" .
			_T('upload_zip_telquel').
			"</div>".
			"<div><input type='radio' name='sousaction5' value='6'>".
			_T('upload_zip_decompacter').
			"</div>".
			"<ul><li>" .
			join("</li>\n<li>",$fichiers) .
			"</li></ul>".
			"<div>&nbsp;</div>".
			"<div style='text-align: right;'><input class='fondo' style='font-size: 9px;' type='submit' value='".
			_T('bouton_valider').
			"'></div>",
			$args, $action);
	install_fin_html();
}

//
// Traiter la liste des fichiers
//

function examiner_les_fichiers($files, $mode, $type, $id, $id_document, $hash, $hash_id_auteur, $redirect, &$actifs)
{
	if (function_exists('gzopen') 
	AND !($mode == 'distant')
	AND (count($files) == 1)) {

		$desc = $files[0];
		if (preg_match('/\.zip$/i', $desc['name'])
		    OR ($desc['type'] == 'application/zip')) {
	
	  // on pose le fichier dans le repertoire zip 
	  // (nota : copier_document n'ecrase pas un fichier avec lui-meme
	  // ca autorise a boucler)
			$zip = copier_document("zip",
					$desc['name'],
					$desc['tmp_name']
				);
			if (!$zip) die ('Erreur upload zip'); # pathologique
			// Est-ce qu'on sait le lire ?
			require_once(_DIR_RESTREINT . 'pclzip.lib.php');
			$archive = new PclZip($zip);
			if ($archive) {
			  // demander confirmation
			  afficher_compactes(verifier_compactes($archive),
					     array(
					 'redirect' => $redirect,
					 'hash' => $hash,
					 'hash_id_auteur' => $hash_id_auteur,
					 'chemin' => $zip,
					 'mode' => $mode,
					 'type' => $type),
				   "spip_image.php3?id_article=$id");
			  // a tout de suite en joindre5 ou joindre6
			  exit;
			}
		}
	}
	foreach ($files as $arg) {
		check_upload_error($arg['error']);
		ajouter_un_document($arg['tmp_name'], $arg['name'], 
				    $type, $id, $mode, $id_document, $actifs);
	}
}

//
// Fonctions referencees dans spip-image par calcul
//

// Cas d'un document distant reference sur internet

function joindre2($arg, $mode, $type, $id, $id_document,$hash, $hash_id_auteur, $redirect, &$actifs)
{
	examiner_les_fichiers(array(
				   array('name' => basename($arg),
					 'tmp_name' => $arg)
				   ), 'distant', $type, $id, $id_document,
			     $hash, $hash_id_auteur, $redirect, $actifs);
}

// Cas d'un fichier transmis

function joindre1($arg, $mode, $type, $id, $id_document,$hash, $hash_id_auteur, $redirect, &$actifs)
{
	$files = array();
	if (is_array($arg))
	  foreach ($arg as $file) {
		if (!$file['error'] == 4 /* UPLOAD_ERR_NO_FILE */)
			$files[]=$file;
	}
	examiner_les_fichiers($files, $mode, $type, $id, $id_document,
			     $hash, $hash_id_auteur, $redirect, $actifs);
} 

// copie de tout ou partie du repertoire upload

function joindre3($arg, $mode, $type, $id, $id_document,$hash, $hash_id_auteur, $redirect, &$actifs)
{
	if (!$arg || strstr($arg, '..')) return;
	    
	$upload = (_DIR_TRANSFERT .$arg);

	if (!is_dir($upload))
	  // seul un fichier est demande
	  $files = array(array ('name' => basename($upload),
				'tmp_name' => $upload)
			 );
	else {
	  $files = array();
	  foreach (fichiers_upload($upload) as $fichier) {
			$files[]= array (
					'name' => basename($fichier),
					'tmp_name' => $fichier
					);
	  }
	}

	examiner_les_fichiers($files, $mode, $type, $id, $id_document,
			     $hash, $hash_id_auteur, $redirect, $actifs);
}

//  identifie les repertoires de upload aux rubriques Spip

function joindre4($arg, $mode, $type, $id, $id_document, $hash, $hash_id_auteur, $redirect, &$documents_actifs)
{
	if (!$arg || strstr($arg, '..')) return;
	$upload = (_DIR_TRANSFERT .$arg);
	identifie_repertoire_et_rubrique($upload, $id, $id_auteur);
	include_ecrire("inc_rubriques.php3");
	calculer_rubriques();
}

//  Zip avec confirmation "tel quel"

function joindre5($arg, $mode, $type, $id, $id_document,$hash, $hash_id_auteur, $redirect, &$actifs)
{
  	ajouter_un_document($arg, basename($arg), $type, $id, $mode, $id_document, $actifs);
}

// cas du zip a deballer. On ressort la bibli 

function joindre6($arg, $mode, $type, $id, $id_document,$hash, $hash_id_auteur, $redirect, &$actifs)
{
	    define('_tmp_dir', creer_repertoire_documents($hash));
	    if (_tmp_dir == _DIR_DOC) die(_L('Op&eacute;ration impossible'));
	    require_once(_DIR_RESTREINT . 'pclzip.lib.php');
	    $archive = new PclZip($arg);
	    $archive->extract(
			      PCLZIP_OPT_PATH, _tmp_dir,
			      PCLZIP_CB_PRE_EXTRACT, 'callback_deballe_fichier'
			      );
	    $contenu = verifier_compactes($archive);
	    //  on supprime la copie temporaire
	    @unlink($arg);
	    
	    foreach ($contenu as $fichier)
		ajouter_un_document(_tmp_dir.basename($fichier),
				    basename($fichier),
				    $type, $id, $mode, $id_document, $actifs);
	    effacer_repertoire_temporaire(_tmp_dir);
}

function identifie_repertoire_et_rubrique($DIR, $id_rubrique, $id_auteur, $art=0)
{
  static $exts = array();

  $handle = @opendir($DIR);
  if (!$handle) {spip_log("$DIR pas lisible") ; return 0;}

  $textes = array();
  $docs = array();
  $rubriques = array();

  // collecte des 3 sortes d'entrees
  while (($entree = readdir($handle)) != '') {
    $chemin = "$DIR/$entree";
    if ($entree[0] !='.') {
      if (is_dir($chemin)) {
	$rubriques[$chemin] = $entree;
    } else {
	if (is_readable($chemin) &&
	    (preg_match("/^([a-zA-Z0-9_].*)\.([a-zA-Z0-9]+)$/", $entree, $match))) {
	  $ext = strtolower($match[2]);
	  if (($ext == 'html') || ($ext == 'htm') || ($ext == 'txt'))
	    $textes[$match[1]] = $match[2];
	  else {
	    if ($ext == 'jpeg') $ext = 'jpg';
	    if (!isset($exts[$ext])) {
	      $req = "SELECT extension FROM spip_types_documents WHERE extension='$ext'";
	      $exts[$ext] = spip_fetch_array(spip_query($req)) ? 'oui' : 'non';
	    }
	    if ($exts[$ext] == 'non') continue;
	    $docs[] = $entree;
	  }
	}
      }
    }
  }
  closedir($handle);

  // integration a la base SQL en commencant par les textes,
  // afin de savoir combien ont ete crees, a chaque niveau.

  if ($textes) {
    ksort($textes);
    foreach($textes as $k=>$v) {
      
	$titre= addslashes(trim(preg_replace('/[-_]/', ' ', $k)));
	$chemin = "$DIR/$k." . $v;
	$texte = file_get_contents($chemin);
	if ($v[0] == 'h') {
	  // cas du html
	  if (preg_match(',<body[^>]*>(.*)</body>,is', $texte, $match))
	    $texte = '<html>'. $match[1] . '</html>';
	}
	$texte = addslashes($texte);
	$date = date("Y-m-d H:i:s", filemtime($chemin));
	$art=spip_abstract_insert($GLOBALS['table_prefix']. "_articles",
				  "(id_rubrique,titre,texte,date,statut)",
				  "($id_rubrique, '$titre', '$texte', '$date', 'prop')");
	spip_abstract_insert($GLOBALS['table_prefix']. "_auteurs_articles", 
			     "(id_auteur,id_article)",
			     "($id_auteur,$art)");
    }
  }

  // si la rubrique a 0 ou 1  article, les documents sont 
  // joints l'article present ou de niveau superieur, sinon a la rubrique.

  $n = count($textes);
  if (($n <= 1) AND $art)
    {$type = 'article'; $id = $art;}
  else
    {$type = 'rubrique'; $id = $id_rubrique; $n = count($docs);}

  if ($docs) {
    foreach($docs as $v) 
	ajouter_un_document ("$DIR/$v",$v,$type,$id,'document',0,$actifs);
  }

  if ($rubriques) {
    foreach ($rubriques as $k => $v)
      {
	$rub=spip_abstract_insert($GLOBALS['table_prefix'] . "_rubriques",
				  "(titre,id_parent,statut)",
				  "('" . addslashes($v) . "', $id_rubrique, 'prepa')");
	$m = identifie_repertoire_et_rubrique($k, $rub, $id_auteur, $art);
	if ($m)
	  $n++;
	else {
	  // faudrait essayer de prevenir plutot que de guerir
	  spip_query("DELETE FROM spip_rubriques WHERE id_rubrique=$rub");
	}
      }
  }
  
  return $n;
 
}


//
// Convertit le type numerique retourne par getimagesize() en extension fichier
//

function decoder_type_image($type, $strict = false) {
	switch ($type) {
	case 1:
		return "gif";
	case 2:
		return "jpg";
	case 3:
		return "png";
	case 4:
		return $strict ? "" : "swf";
	case 5:
		return "psd";
	case 6:
		return "bmp";
	case 7:
	case 8:
		return "tif";
	default:
		return "";
	}
}


//
// Corrige l'extension du fichier dans quelques cas particuliers
//

function corriger_extension($ext) {
	switch ($ext) {
	case 'htm':
		return 'html';
	case 'jpeg':
		return 'jpg';
	case 'tiff':
		return 'tif';
	default:
		return $ext;
	}
}


//
// Ajouter un logo
//

// $source = $_FILES[0]
// $dest = arton12.xxx
function ajout_logo($source, $dest) {

	if (!$source) return;
	if (!is_array($source))
		$f = _DIR_TRANSFERT . $source; // fichier dans upload/
	else {
		// Intercepter une erreur a l'envoi
		if (check_upload_error($source['error'])) return;

		// analyse le type de l'image (on ne fait pas confiance au nom de
		// fichier envoye par le browser : pour les Macs c'est plus sur)
		$f =_DIR_DOC . $dest . '.tmp';
		deplacer_fichier_upload($source['tmp_name'], $f);
	}

	$size = @getimagesize($f);
	$type = decoder_type_image($size[2], true);

	if ($type) {
		$poids = filesize($f);
		if (_LOGO_MAX_SIZE > 0
		AND $poids > _LOGO_MAX_SIZE*1024) {
			@unlink ($f);
			check_upload_error(6,
			_T('info_logo_max_poids',
				array('maxi' => taille_en_octets(_LOGO_MAX_SIZE*1024),
				'actuel' => taille_en_octets($poids))));
		}

		if (_LOGO_MAX_WIDTH * _LOGO_MAX_HEIGHT
		AND ($size[0] > _LOGO_MAX_WIDTH
		OR $size[1] > _LOGO_MAX_HEIGHT)) {
			@unlink ($f);
			check_upload_error(6, 
			_T('info_logo_max_taille',
				array(
				'maxi' =>
					_T('info_largeur_vignette',
						array('largeur_vignette' => _LOGO_MAX_WIDTH,
						'hauteur_vignette' => _LOGO_MAX_HEIGHT)),
				'actuel' =>
					_T('info_largeur_vignette',
						array('largeur_vignette' => $size[0],
						'hauteur_vignette' => $size[1]))
			)));
		}
		@rename ($f, _DIR_DOC . $dest . ".$type");
	}
	else {
		@unlink ($f);
		check_upload_error(6,
			_T('info_logo_format_interdit',
			array ('formats' => 'GIF, JPG, PNG'))
		);
	}
}


function effacer_logo($nom) {
	if (!strstr($nom, ".."))
		@unlink(_DIR_IMG . $nom);
}



//
// Creation automatique de vignette
//

// Tester nos capacites
function tester_vignette ($test_vignette) {
	global $pnmscale_command;

	// verifier les formats acceptes par GD
	if ($test_vignette == "gd1") {
		// Si GD est installe et php >= 4.0.2
		if (function_exists('imagetypes')) {

			if (imagetypes() & IMG_GIF) {
				$gd_formats[] = "gif";
			} else {
				# Attention GD sait lire le gif mais pas forcement l'ecrire
				if (function_exists('ImageCreateFromGIF')) {
					$srcImage = @ImageCreateFromGIF(_DIR_IMG . "test.gif");
					if ($srcImage) {
						$gd_formats_read_gif = ",gif";
						ImageDestroy( $srcImage );
					}
				}
			}

			if (imagetypes() & IMG_JPG)
				$gd_formats[] = "jpg";
			if (imagetypes() & IMG_PNG)
				$gd_formats[] = "png";
		}

		else {	# ancienne methode de detection des formats, qui en plus
				# est bugguee car elle teste les formats en lecture
				# alors que la valeur deduite sert a identifier
				# les formats disponibles en ecriture... (cf. inc_logos.php3)
		
			$gd_formats = Array();
			if (function_exists('ImageCreateFromJPEG')) {
				$srcImage = @ImageCreateFromJPEG(_DIR_IMG . "test.jpg");
				if ($srcImage) {
					$gd_formats[] = "jpg";
					ImageDestroy( $srcImage );
				}
			}
			if (function_exists('ImageCreateFromGIF')) {
				$srcImage = @ImageCreateFromGIF(_DIR_IMG . "test.gif");
				if ($srcImage) {
					$gd_formats[] = "gif";
					ImageDestroy( $srcImage );
				}
			}
			if (function_exists('ImageCreateFromPNG')) {
				$srcImage = @ImageCreateFromPNG(_DIR_IMG . "test.png");
				if ($srcImage) {
					$gd_formats[] = "png";
					ImageDestroy( $srcImage );
				}
			}
		}

		if ($gd_formats) $gd_formats = join(",", $gd_formats);
		ecrire_meta("gd_formats_read", $gd_formats.$gd_formats_read_gif);
		ecrire_meta("gd_formats", $gd_formats);
		ecrire_metas();
	}

	// verifier les formats netpbm
	else if ($test_vignette == "netpbm"
	AND $pnmscale_command) {
		$netpbm_formats= Array();

		$jpegtopnm_command = str_replace("pnmscale",
			"jpegtopnm", $pnmscale_command);
		$pnmtojpeg_command = str_replace("pnmscale",
			"pnmtojpeg", $pnmscale_command);

		$vignette = _DIR_IMG . "test.jpg";
		$dest = _DIR_IMG . "test-jpg.jpg";
		$commande = "$jpegtopnm_command $vignette | $pnmscale_command -width 10 | $pnmtojpeg_command > $dest";
		spip_log($commande);
		exec($commande);
		if ($taille = @getimagesize($dest)) {
			if ($taille[1] == 10) $netpbm_formats[] = "jpg";
		}
		$giftopnm_command = ereg_replace("pnmscale", "giftopnm", $pnmscale_command);
		$pnmtojpeg_command = ereg_replace("pnmscale", "pnmtojpeg", $pnmscale_command);
		$vignette = _DIR_IMG . "test.gif";
		$dest = _DIR_IMG . "test-gif.jpg";
		$commande = "$giftopnm_command $vignette | $pnmscale_command -width 10 | $pnmtojpeg_command > $dest";
		spip_log($commande);
		exec($commande);
		if ($taille = @getimagesize($dest)) {
			if ($taille[1] == 10) $netpbm_formats[] = "gif";
		}

		$pngtopnm_command = ereg_replace("pnmscale", "pngtopnm", $pnmscale_command);
		$vignette = _DIR_IMG . "test.png";
		$dest = _DIR_IMG . "test-gif.jpg";
		$commande = "$pngtopnm_command $vignette | $pnmscale_command -width 10 | $pnmtojpeg_command > $dest";
		spip_log($commande);
		exec($commande);
		if ($taille = @getimagesize($dest)) {
			if ($taille[1] == 10) $netpbm_formats[] = "png";
		}
		

		if ($netpbm_formats)
			$netpbm_formats = join(",", $netpbm_formats);
		else
			$netpbm_formats = '';
		ecrire_meta("netpbm_formats", $netpbm_formats);
		ecrire_metas();
	}

	// et maintenant envoyer la vignette de tests
	if (ereg("^(gd1|gd2|imagick|convert|netpbm)$", $test_vignette)) {
		include_ecrire('inc_logos.php3');
		//$taille_preview = lire_meta("taille_preview");
		if ($taille_preview < 10) $taille_preview = 150;
		if ($preview = creer_vignette(_DIR_IMG . 'test_image.jpg', $taille_preview, $taille_preview, 'jpg', '', "test_$test_vignette", $test_vignette, true))

			return ($preview['fichier']);
	}

	return _DIR_IMG_PACK . 'puce-rouge-anim.gif';	# image echec
}

// Creation
function creer_fichier_vignette($vignette, $test_cache_only=false) {
	if ($vignette && lire_meta("creer_preview") == 'oui') {
		eregi('\.([a-z0-9]+)$', $vignette, $regs);
		$ext = $regs[1];
		$taille_preview = lire_meta("taille_preview");
		if ($taille_preview < 10) $taille_preview = 120;
		include_ecrire('inc_logos.php3');

		if ($preview = creer_vignette($vignette, $taille_preview, $taille_preview, $ext, 'vignettes', basename($vignette).'-s', 'AUTO', false, $test_cache_only))
		{
			inserer_vignette_base($vignette, $preview['fichier']);
			return $preview['fichier'];
		}
		include_ecrire('inc_documents.php3');
		return vignette_par_defaut($ext ? $ext : 'txt', false);
	}
}

// Insertion d'une vignette dans la base
function inserer_vignette_base($image, $vignette) {

	$taille = @filesize($vignette);
	
	$size = @getimagesize($vignette);
	$largeur = $size[0];
	$hauteur = $size[1];
	$type = $size[2];

	if ($type == "2") $format = 1;			# spip_types_documents
	else if ($type == "3") $format = 2;
	else if ($type == "1") $format = 3;
	else return;

	$vignette = str_replace('../', '', $vignette);

	spip_log("creation vignette($image) -> $vignette");

	if ($t = spip_query("SELECT id_document FROM spip_documents
	WHERE fichier='".addslashes($image)."'")) {
		if ($row = spip_fetch_array($t)) {
			$id_document = $row['id_document'];
			$id_vignette = spip_abstract_insert("spip_documents", 
				"(mode)",
				"('vignette')");
			spip_query("UPDATE spip_documents
				SET id_vignette=$id_vignette WHERE id_document=$id_document");
			spip_query("UPDATE spip_documents SET
				id_type = '$format',
				largeur = '$largeur',
				hauteur = '$hauteur',
				taille = '$taille',
				fichier = '$vignette',
				date = NOW()
				WHERE id_document = $id_vignette");
			spip_log("(document=$id_document, vignette=$id_vignette)");
		}
	}
}


// Effacer un doc (et sa vignette)
function supprime_document_et_vignette($doc_supp) {

	$result = spip_query("SELECT id_vignette, fichier
		FROM spip_documents
		WHERE id_document=$doc_supp");
	if ($row = spip_fetch_array($result)) {
		$fichier = $row['fichier'];
		$id_vignette = $row['id_vignette'];
		spip_query("DELETE FROM spip_documents
			WHERE id_document=$doc_supp");
		spip_query("UPDATE spip_documents SET id_vignette=0
			WHERE id_vignette=$doc_supp");
		spip_query("DELETE FROM spip_documents_articles
			WHERE id_document=$doc_supp");
		spip_query("DELETE FROM spip_documents_rubriques
			WHERE id_document=$doc_supp");
		spip_query("DELETE FROM spip_documents_breves
			WHERE id_document=$doc_supp");
		@unlink($fichier);

		if ($id_vignette > 0) {
			$query = "SELECT id_vignette, fichier FROM spip_documents
				WHERE id_document=$id_vignette";
			$result = spip_query($query);
			if ($row = spip_fetch_array($result)) {
				$fichier = $row['fichier'];
				@unlink($fichier);
			}
			spip_query("DELETE FROM spip_documents
				WHERE id_document=$id_vignette");
			spip_query("DELETE FROM spip_documents_articles
				WHERE id_document=$id_vignette");
			spip_query("DELETE FROM spip_documents_rubriques
				WHERE id_document=$id_vignette");
			spip_query("DELETE FROM spip_documents_breves
				WHERE id_document=$id_vignette");
		}
	}
}



/////////////////////////////////////////////////////////////////////
//
// Faire tourner une image
//
function gdRotate ($imagePath,$rtt){
	if(preg_match("/\.(png|gif|jpe?g|bmp)$/i", $imagePath, $regs)) {
		switch($regs[1]) {
			case 'png':
				$src_img=ImageCreateFromPNG($imagePath);
				$save = 'imagepng';
				break;
			case 'gif':
				$src_img=ImageCreateFromGIF($imagePath);
				$save = 'imagegif';
				break;
			case 'jpeg':
			case 'jpg':
				$src_img=ImageCreateFromJPEG($imagePath);
				$save = 'Imagejpeg';
				break;
			case 'bmp':
				$src_img=ImageCreateFromWBMP($imagePath);
				$save = 'imagewbmp';
				break;
			default:
				return false;
		}
	}

	if (!$src_img) {
		spip_log("gdrotate: image non lue, $imagePath");
		return false;
	}

	$size=@getimagesize($imagePath);
	if (!($size[0] * $size[1])) return false;

	if (function_exists('imagerotate')) {
		$dst_img = imagerotate($src_img, -$rtt, 0);
	} else {

	// Creer l'image destination (hauteur x largeur) et la parcourir
	// pixel par pixel (un truc de fou)
	$process = lire_meta('image_process');
	if ($process == "gd2")
		$dst_img=ImageCreateTrueColor($size[1],$size[0]);
	else
		$dst_img=ImageCreate($size[1],$size[0]);

	if($rtt==90){
		$t=0;
		$b=$size[1]-1;
		while($t<=$b){
			$l=0;
			$r=$size[0]-1;
			while($l<=$r){
				imagecopy($dst_img,$src_img,$t,$r,$r,$b,1,1);
				imagecopy($dst_img,$src_img,$t,$l,$l,$b,1,1);
				imagecopy($dst_img,$src_img,$b,$r,$r,$t,1,1);
				imagecopy($dst_img,$src_img,$b,$l,$l,$t,1,1);
				$l++;
				$r--;
			}
			$t++;
			$b--;
		}
	}
	elseif($rtt==-90){
		$t=0;
		$b=$size[1]-1;
		while($t<=$b){
			$l=0;
			$r=$size[0]-1;
			while($l<=$r){
				imagecopy($dst_img,$src_img,$t,$l,$r,$t,1,1);
				imagecopy($dst_img,$src_img,$t,$r,$l,$t,1,1);
				imagecopy($dst_img,$src_img,$b,$l,$r,$b,1,1);
				imagecopy($dst_img,$src_img,$b,$r,$l,$b,1,1);
				$l++;
				$r--;
			}
			$t++;
			$b--;
		}
	}
	}
	ImageDestroy($src_img);
	ImageInterlace($dst_img,0);

	# obligatoire d'enregistrer dans le meme format, puisque c'est
	# dans le fichier de depart...
	$save($dst_img,$imagePath);
}

function tourner_document($var_rot, $doc_rotate, $convert_command) {
	
	$var_rot = intval($var_rot);

	$query = "SELECT id_vignette, fichier FROM spip_documents WHERE id_document=$doc_rotate";
	$result = spip_query($query);
	if ($row = spip_fetch_array($result)) {
		$id_vignette = $row['id_vignette'];
		$image = $row['fichier'];

		$process = lire_meta('image_process');

		 // imagick (php4-imagemagick)
		 if ($process == 'imagick') {
			$handle = imagick_readimage($image);
			imagick_rotate($handle, $var_rot);
			imagick_write($handle, $image);
			if (!@file_exists($image)) return;	// echec imagick
		}
		else if ($process == "gd2") { // theoriquement compatible gd1, mais trop forte degradation d'image
			if ($var_rot == 180) { // 180 = 90+90
				gdRotate ($image, 90);
				gdRotate ($image, 90);
			} else {
				gdRotate ($image, $var_rot);
			}
		}
		else if ($process = "convert") {
			$commande = "$convert_command -rotate $var_rot ./"
				. escapeshellcmd($image).' ./'.escapeshellcmd($image);
#			spip_log($commande);
			exec($commande);
		}

		$size_image = @getimagesize($image);
		$largeur = $size_image[0];
		$hauteur = $size_image[1];

/*
	A DESACTIVER PEUT-ETRE ? QUE SE PASSE--IL SI JE TOURNE UNE IMAGE AYANT UNE VGNETTE "MANUELLE" -> NE PAS CREER DE VIGNETTE TOURNEE -- EN VERITE IL NE FAUT PAS PERMETTRE DE TOURNER UNE IMAGE AYANT UNE VIGNETTE MANUELLE
		if ($id_vignette > 0) {
			creer_fichier_vignette($image);
		}
*/

		spip_query("UPDATE spip_documents SET largeur=$largeur, hauteur=$hauteur WHERE id_document=$doc_rotate");

	}
}

?>
