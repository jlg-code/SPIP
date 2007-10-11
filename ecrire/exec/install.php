<?php

/***************************************************************************\
 *  SPIP, Systeme de publication pour l'internet                           *
 *                                                                         *
 *  Copyright (c) 2001-2007                                                *
 *  Arnaud Martin, Antoine Pitrou, Philippe Riviere, Emmanuel Saint-James  *
 *                                                                         *
 *  Ce programme est un logiciel libre distribue sous licence GNU/GPL.     *
 *  Pour plus de details voir le fichier COPYING.txt ou l'aide en ligne.   *
\***************************************************************************/

if (!defined("_ECRIRE_INC_VERSION")) return;

include_spip('inc/minipres');

define("_ECRIRE_INSTALL", "1");
define('_FILE_TMP', '_install');

// http://doc.spip.org/@exec_install_dist
function exec_install_dist()
{
	$etape = _request('etape');
	if (_FILE_CONNECT AND ($etape != 'chmod')) {
  // L'etape chmod peut etre reexecutee n'importe quand apres l'install,
  // pour verification des chmod. Sinon, install deja faite => refus.
		echo minipres();
		exit;
	}
	include_spip('base/create');
	$fonc = charger_fonction("etape_$etape", 'install');
	$fonc();
}

//  Pour ecrire les fichiers memorisant les parametres de connexion

// http://doc.spip.org/@install_fichier_connexion
function install_fichier_connexion($nom, $texte)
{
	$texte = "<"."?php\n"
	. "if (!defined(\"_ECRIRE_INC_VERSION\")) return;\n"
	. $texte 
	. "?".">";

	ecrire_fichier($nom, $texte);
}

function analyse_fichier_connection($file)
{
  
	$s = @join('', @file($file));
	if (preg_match("#mysql_connect\([\"'](.*)[\"'],[\"'](.*)[\"'],[\"'](.*)[\"']\)#", $s, $regs)) {
		array_shift($regs);
		return $regs;
	} else if (preg_match("#spip_connect_db\('([^']*)','([^']*)','([^']*)','(.*)'#", $s, $regs)) {
			if ($port_db = $regs[2]) $regs[1] .= ':'.$port_db;
			$regs[2] = $regs[3];
			array_shift($regs);
			return $regs;
	}
	return '';
}

function bases_referencees($exclu='')
{
	$tables = array();
	foreach(preg_files(_DIR_CONNECT, '.php$') as $f) {
		if ($f != $exclu AND analyse_fichier_connection($f))
			$tables[]= basename($f, '.php');
	}
	return $tables;
}


//
// Verifier que l'hebergement est compatible SPIP ... ou l'inverse :-)
// (sert a l'etape 1 de l'installation)
// http://doc.spip.org/@tester_compatibilite_hebergement
function tester_compatibilite_hebergement() {
	$err = array();

	$p = phpversion();
	if (preg_match(',^([0-9]+)\.([0-9]+)\.([0-9]+),', $p, $regs)) {
		$php = array($regs[1], $regs[2], $regs[3]);
		$m = '4.0.8';
		$min = explode('.', $m);
		if ($php[0]<$min[0]
		OR ($php[0]==$min[0] AND $php[1]<$min[1])
		OR ($php[0]==$min[0] AND $php[1]==$min[1] AND $php[2]<$min[2]))
			$err[] = _T('install_php_version', array('version' => $p,  'minimum' => $m));
	}

	if (!function_exists('mysql_query'))
		$err[] = _T('install_extension_php_obligatoire')
		. " <a href='http://se.php.net/mysql'>MYSQL</a>";

	if (!function_exists('preg_match_all'))
		$err[] = _T('install_extension_php_obligatoire')
		. " <a href='http://se.php.net/pcre'>PCRE</a>";

	if ($a = @ini_get('mbstring.func_overload'))
		$err[] = _T('install_extension_mbstring')
		. "mbstring.func_overload=$a - <a href='http://se.php.net/mb_string'>mb_string</a>.<br /><small>";

	if ($err) {
			echo "<p class='verdana1 spip_large'><b>"._T('avis_attention').'</b></p><p>'._T('install_echec_annonce')."</p><ul>";
		while (list(,$e) = each ($err))
			echo "<li>$e</li>\n";

		# a priori ici on pourrait die(), mais il faut laisser la possibilite
		# de forcer malgre tout (pour tester, ou si bug de detection)
		echo "</ul><hr />\n";
	}
}


// Une fonction pour faciliter la recherche du login (superflu ?)
// http://doc.spip.org/@login_hebergeur
function login_hebergeur() {
	global $HTTP_X_HOST, $REQUEST_URI, $SERVER_NAME, $HTTP_HOST;

	$base_hebergeur = 'localhost'; # par defaut

	// Lycos (ex-Multimachin)
	if ($HTTP_X_HOST == 'membres.lycos.fr') {
		preg_match(',^/([^/]*),', $REQUEST_URI, $regs);
		$login_hebergeur = $regs[1];
	}
	// Altern
	else if (preg_match(',altern\.com$,', $SERVER_NAME)) {
		preg_match(',([^.]*\.[^.]*)$,', $HTTP_HOST, $regs);
		$login_hebergeur = preg_replace('[^\w\d]', '_', $regs[1]);
	}
	// Free
	else if (preg_match(',(.*)\.free\.fr$,', $SERVER_NAME, $regs)) {
		$base_hebergeur = 'sql.free.fr';
		$login_hebergeur = $regs[1];
	} else $login_hebergeur = '';

	return array($base_hebergeur, $login_hebergeur);
}


// http://doc.spip.org/@info_etape
function info_etape($titre, $complement = ''){
	return "<h2>".$titre."</h2>\n" .
	($complement ? "<br />".$complement."\n":'');
}

// http://doc.spip.org/@bouton_suivant
function bouton_suivant($code = '') {
	if($code=='') $code = _T('bouton_suivant');
	static $suivant = 0;
	$id = 'suivant'.(($suivant>0)?strval($suivant):'');
	$suivant +=1;
	return "\n<span class='suivant'><input id='".$id."' type='submit' class='fondl'\nvalue=\"" .
		$code .
		" >>\" /></span>\n";
}

// http://doc.spip.org/@info_progression_etape
function info_progression_etape($en_cours,$phase,$dir){
	//$en_cours = _request('etape')?_request('etape'):"";
	$liste = find_all_in_path($dir,$phase.'(([0-9])+|fin)[.]php$');
	$debut = 1; $etat = "ok";
	$last = count($liste);
	
	$aff_etapes = "<span id='etapes'>";
	foreach($liste as $etape=>$fichier){
		if ($etape=="$phase$en_cours.php"){
			$etat = "encours";
		}
		$aff_etapes .= ($debut<$last)
			? "<span class='$etat'><em>$debut</em><span>,</span> </span>"
			: '';
		if ($etat == "encours")
			$etat = 'todo';
		$debut++;
	}
	$aff_etapes .= "<br class='nettoyeur' />&nbsp;</span>\n";
	return $aff_etapes;
}


// http://doc.spip.org/@fieldset
function fieldset($legend, $champs = array(), $horchamps='') {
	$fieldset = "<fieldset>\n" .
	($legend ? "<legend>".$legend."</legend>\n" : '');
	foreach ($champs as $nom => $contenu) {
		$type = isset($contenu['hidden']) ? 'hidden' : (preg_match(',^pass,', $nom) ? 'password' : 'text');
		$class = isset($contenu['hidden']) ? '' : "class='formo' size='40' ";
		if(isset($contenu['alternatives'])) {
			$fieldset .= $contenu['label'] ."\n";
			foreach($contenu['alternatives'] as $valeur => $label) {
				$fieldset .= "<input type='radio' name='".$nom .
				"' id='$nom-$valeur' value='$valeur'"
				  .(($valeur==$contenu['valeur'])?"\nchecked='checked'":'')."/>\n";
				$fieldset .= "<label for='$nom-$valeur'>".$label."</label>\n";
			}
			$fieldset .= "<br />\n";
		}
		else {
			$fieldset .= "<label for='".$nom."'>".$contenu['label']."</label>\n";
			$fieldset .= "<input ".$class."type='".$type."' id='" . $nom . "' name='".$nom."'\nvalue='".$contenu['valeur']."' />\n";
		}
	}
	$fieldset .= "$horchamps</fieldset>\n";
	return $fieldset;
}

function install_connexion_form($db, $login, $pass, $predef, $hidden, $etape)
{
	$pg = function_exists('pg_connect');
	$mysql = function_exists('mysql_connect');

	if ($predef[0] AND !is_string($predef[0]))
		$server_db = _INSTALL_SERVER_DB;
	else if (!($pg AND $mysql))
		$server_db = $mysql ? 'mysql' : 'pg';
	else {
	  $server_db ='';
	  $m = ($predef != 'mysql') ? '' : " selected='selected'";
	  $p = ($predef != 'pg') ? '' : " selected='selected'";
	}

	return generer_form_ecrire('install', (
	  "\n<input type='hidden' name='etape' value='$etape' />" 
	. $hidden
	. (_request('echec')?
			("<p><b>"._T('avis_connexion_echec_1').
			"</b></p><p>"._T('avis_connexion_echec_2')."</p><p style='font-size: small;'>"._T('avis_connexion_echec_3')."</p>")
			:"")

	. ($server_db
		? '<input type="hidden" name="server_db" value="'.$server_db.'" />'
		: ('<fieldset><legend>'
		._L('Indiquer le type de base de donn&eacute;es :')
		. "\n<select name='server_db'>"
		. ($mysql
			? "<option value='mysql'$m>"._L('MySQL')."</option>"
			: '')
		. ($pg
			? "<option value='pg'$p>"._L('PostGreSQL')."</option>"
			: '')
		   . "</select></legend></fieldset>")
	)

	. ($predef[1]
	? '<h3>'._T('install_adresse_base_hebergeur').'</h3>'
	: fieldset(_T('entree_base_donnee_1'),
		array(
			'adresse_db' => array(
				'label' => $db[1],
				'valeur' => $db[0]
			),
		)
	)
	)

	. ($predef[2]
	? '<h3>'._T('install_login_base_hebergeur').'</h3>'
	: fieldset(_T('entree_login_connexion_1'),
		array(
			'login_db' => array(
					'label' => $login[1],
					'valeur' => $login[0]
			),
		)
	)
	)

	. ($predef[3]
	? '<h3>'._T('install_pass_base_hebergeur').'</h3>'
	: fieldset(_T('entree_mot_passe_1'),
		array(
			'pass_db' => array(
				'label' => $pass[1],
				'valeur' => $pass[0]
			),
		)
	)
	)

	. bouton_suivant()));

}

// 4 valeurs qu'on reconduit d'un script a l'autre
// sauf s'ils sont predefinis.

function predef_ou_cache($adresse_db, $login_db, $pass_db, $server_db)
{
	return (defined('_INSTALL_HOST_DB')
		? ''
		: "\n<input type='hidden' name='adresse_db'  value=\"".htmlspecialchars($adresse_db)."\" />"
	)
	. (defined('_INSTALL_USER_DB')
		? ''
		: "\n<input type='hidden' name='login_db' value=\"".htmlspecialchars($login_db)."\" />"
	)
	. (defined('_INSTALL_PASS_DB')
		? ''
		: "\n<input type='hidden' name='pass_db' value=\"".htmlspecialchars($pass_db)."\" />"
	)

	. (defined('_INSTALL_SERVER_DB')
		? ''
		: "\n<input type='hidden' name='server_db' value=\"".htmlspecialchars($server_db)."\" />"
	   );
}

// presentation des bases existantes

function install_etape_liste_bases($server_db, $disabled=array())
{
	$result = sql_listdbs($server_db);
	if (!$result) return '';
	$bases = $checked = array();

	while ($row = sql_fetch($result, $server_db)) {

		$nom = array_shift($row);
		$id = htmlspecialchars($nom);
		$dis = in_array($nom, $disabled) ? " disabled='disabled'" : '';
		$base = " name=\"choix_db\" value=\""
		  . $nom
		  . '"'
		  . $dis
		  . " type='radio' id='$id'";
		$label = "<label for='$id'>"
		. ($dis ? "<i>$nom</i>" : $nom)
		. "</label>";

		if (!$checked AND !$dis AND
		    (($nom == $login_db) OR
			($GLOBALS['table_prefix'] == $nom))) {
			$checked = "<input$base checked='checked' />\n$label";
		} else {
			$bases[]= "<input$base />\n$label";
		}
	}
	if (!$bases) return false;

	if ($checked) {array_unshift($bases, $checked); $checked = true;}

	return array($checked, $bases);
}
?>
