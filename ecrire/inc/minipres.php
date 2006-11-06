<?php

/***************************************************************************\
 *  SPIP, Systeme de publication pour l'internet                           *
 *                                                                         *
 *  Copyright (c) 2001-2006                                                *
 *  Arnaud Martin, Antoine Pitrou, Philippe Riviere, Emmanuel Saint-James  *
 *                                                                         *
 *  Ce programme est un logiciel libre distribue sous licence GNU/GPL.     *
 *  Pour plus de details voir le fichier COPYING.txt ou l'aide en ligne.   *
\***************************************************************************/

if (!defined("_ECRIRE_INC_VERSION")) return;

include_spip('inc/lang');

//
// Presentation des pages d'installation et d'erreurs
//

// http://doc.spip.org/@install_debut_html
function install_debut_html($titre = 'AUTO', $onLoad = '') {
	global $spip_lang_right;
	
	include_spip('inc/filtres');
	include_spip('inc/headers');
	utiliser_langue_visiteur();

	http_no_cache();

	if ($titre=='AUTO')
		$titre=_T('info_installation_systeme_publication');

	# le charset est en utf-8, pour recuperer le nom comme il faut
	# lors de l'installation
	if (!headers_sent())
		header('Content-Type: text/html; charset=utf-8');

	echo  _DOCTYPE_ECRIRE ,
		html_lang_attributes(),
		"<head>\n",
		"<title>",
		textebrut($titre),
		"</title>
		<style type='text/css'><!--\n/*<![CDATA[*/\n\n\n",
		"body { background: #FFF; color: #000; }\n",
		"h1 { color: #970038; margin-top: 50px; font-family: Verdana; font-weigth: bold; font-size: 18px }\n",
		"h2 { font-family: Verdana,Arial,Sans,sans-serif; font-weigth: normal; font-size: 100%; }\n",
		"a { color: #E86519; text-decoration: none; }\n",
		"a:visited { color: #6E003A; }\n",
		"a:active { color: #FF9900; }\n",
		"img { border: 0; }\n",
		"p { text-align: justify; }\n",
		"ul { text-align: justify; list-style-type: none; }\n",
		"fieldset, .fieldset { font-weigth: bold; text-align: justify; border: 1px solid #444; paddind: 10px; margin-top: 1em; }\n",
		"legend { font-weight: bold; }\n",
		"label {}\n",
		"#minipres { width: 30em; text-align: center; margin-left: auto; margin-right: auto; }\n",
		".petit-centre { font-family: Verdana,Arial,Sans,sans-serif; font-size: 10px; }\n",
		".petit-centre p { text-align: center; }\n",
		".suivant { text-align: $spip_lang_right; display: block; margin-top: 1em; }\n",
		".fondl { padding: 3px; background-color: #eee; border: 1px solid #333; 
	background-position: center bottom; 
	font-size: 0.8em;
	font-family: Verdana,Arial,Sans,sans-serif; }\n",
		".formo { width: 100%; display: block; padding: 3px;
	margin-top: 1em;
	background-color: #FFF; 
	border: 1px solid #333; 
	background-position: center bottom; 
	behavior: url(../dist/win_width.htc);
	font-size: 0.8em;
	font-family: Verdana,Arial,Sans,sans-serif; }\n",
	  "\n\n]]>\n--></style>\n\n
</head>
<body".$onLoad.">
	<div id='minipres'>
	<h1>",
	  $titre ,
	  "</h1>
	<div>\n";
}

// http://doc.spip.org/@install_fin_html
function install_fin_html() {
	echo "\n\t</div>\n\t</div>\n</body>\n</html>";
}

// http://doc.spip.org/@info_etape
function info_etape($titre, $complement = ''){
	return "\n<h2>".$titre."</h2>\n" .
	($complement ? "<p>".$complement."</p>\n":'');
}

// http://doc.spip.org/@fieldset
function fieldset($legend, $champs = array()) {
	$fieldset = "<fieldset>\n" .
	($legend ? "<legend>".$legend."</legend>\n" : '');
	foreach($champs as $nom => $contenu) {
		$type = $contenu['hidden'] ? 'hidden' : (preg_match(',^pass,', $nom) ? 'password' : 'text');
		$class = $contenu['hidden'] ? '' : "class='formo' size='40' ";
		$fieldset .= "<label for='".$nom."'>".$contenu['label']."</label>\n";
		$fieldset .= "<input ".$class."type='".$type."' name='".$nom."' value='".$contenu['valeur']."' />\n";
	}
	$fieldset .= "</fieldset>\n";
	return $fieldset;
}

// http://doc.spip.org/@bouton_suivant
function bouton_suivant($code = 'bouton_suivant') {
	return "\n<span class='suivant'><input id='suivant' type='submit' class='fondl' value=\"" .
		_T($code) .
		" >>\" /></span>\n";
}

// http://doc.spip.org/@minipres
function minipres($titre, $corps="")
{
	if (!$titre)
		echo  _DOCTYPE_ECRIRE ,
		  html_lang_attributes(),
		  "<body>",
		  $corps,
		  '</body></html>';
	else {
		install_debut_html($titre);
		echo $corps;
		install_fin_html();
	}
	exit;
}

//
// Aide. Surchargeable, et pas d'ereur fatale si pas disponible.
//

// http://doc.spip.org/@aide
function aide($aide='') {
	$aider = charger_fonction('aider', 'inc', true);
	return $aider ?  $aider($aide) : '';
}


//
// Mention de la revision SVN courante de l'espace restreint standard
// (numero non garanti pour l'espace public et en cas de mutualisation)
// on est negatif si on est sur .svn, et positif si on utilise svn.revision
// http://doc.spip.org/@version_svn_courante
function version_svn_courante($dir) {
	if (!$dir) $dir = '.';

	// version installee par SVN
	if (lire_fichier($dir . '/.svn/entries', $c)
	AND (
	(preg_match_all(
	',committed-rev="([0-9]+)",', $c, $r1, PREG_PATTERN_ORDER)
	AND $v = max($r1[1])
	)
	OR
	(preg_match(',^8.*dir[\r\n]+(\d+),ms', $c, $r1) # svn >= 1.4
	AND $v = $r1[1]
	)))
		return -$v;

	// version installee par paquet ZIP de SPIP-Zone
	if (lire_fichier($dir.'/svn.revision', $c)
	AND preg_match(',Revision: (\d+),', $c, $d))
		return intval($d[1]);

	// Bug ou paquet fait main
	return 0;
}

// http://doc.spip.org/@info_copyright
function info_copyright() {
	global $spip_version_affichee, $spip_lang;

	$version = $spip_version_affichee;

	//
	// Mention, le cas echeant, de la revision SVN courante
	//
	if ($svn_revision = version_svn_courante(_DIR_RACINE)) {
		$version .= ' ' . (($svn_revision < 0) ? 'SVN ':'')
		. "[<a href='http://trac.rezo.net/trac/spip/changeset/"
		. abs($svn_revision) . "' target='_blank'>"
		. abs($svn_revision) . "</a>]";
	}

	return _T('info_copyright', 
		   array('spip' => "<b>SPIP $version</b> ",
			 'lien_gpl' => 
			 "<a href='". generer_url_ecrire("aide_index", "aide=licence&var_lang=$spip_lang") . "' target='spip_aide' onclick=\"javascript:window.open(this.href, 'aide_spip', 'scrollbars=yes,resizable=yes,width=740,height=580'); return false;\">" . _T('info_copyright_gpl')."</a>"));

}

// normalement il faudrait creer exec/info.php, mais pour mettre juste ca:

// http://doc.spip.org/@exec_info_dist
function exec_info_dist() {
	global $connect_statut;
	if ($connect_statut == '0minirezo') phpinfo();
}

// Idem faudrait creer exec/test_ajax, mais c'est si court.
// Tester si Ajax fonctionne pour ce brouteur
// (si on arrive la c'est que c'est bon, donc poser le cookie)

// http://doc.spip.org/@exec_test_ajax_dist
function exec_test_ajax_dist() {
	switch (_request('js')) {
		// on est appele par <noscript>
		case -1:
			spip_setcookie('spip_accepte_ajax', -1);
			redirige_par_entete(_DIR_IMG_PACK.'puce-orange-anim.gif');
			break;

		// ou par ajax
		case 1:
		default:
			spip_setcookie('spip_accepte_ajax', 1);
			break;
	}
}

// Afficher le bouton "preview" dans l'espace public
// http://doc.spip.org/@afficher_bouton_preview
function afficher_bouton_preview() {
		$x = _T('previsualisation');
		return '<div style="
		display: block;
		color: #eeeeee;
		background-color: #111111;
		padding-right: 5px;
		padding-top: 2px;
		padding-bottom: 5px;
		font-size: 20px;
		top: 0px;
		left: 0px;
		position: absolute;
		">' 
		. http_img_pack('naviguer-site.png', $x, '')
		. '&nbsp;' . majuscules($x) . '</div>';
}

// Fabrique une balise A, avec tous les attributs possibles
// attention au cas ou la href est du Javascript avec des "'"
// pour un href conforme au validateur W3C, faire & --> &amp; avant

// http://doc.spip.org/@http_href
function http_href($href, $clic, $title='', $style='', $class='', $evt='') {
	return '<a href="' .
		$href .
		'"' .
		(!$title ? '' : ("\ntitle=\"" . supprimer_tags($title)."\"")) .
		(!$style ? '' : ("\nstyle=\"" . $style . "\"")) .
		(!$class ? '' : ("\nclass=\"" . $class . "\"")) .
		($evt ? "\n$evt" : '') .
		'>' .
		$clic .
		'</a>';
}

// produit une balise img avec un champ alt d'office si vide
// attention le htmlentities et la traduction doivent etre appliques avant.

// http://doc.spip.org/@http_wrapper
function http_wrapper($img){
	static $wrapper_state=NULL;
	static $wrapper_table = array();
	
	if (strpos($img,'/')===FALSE) // on ne prefixe par _DIR_IMG_PACK que si c'est un nom de fichier sans chemin
		$f = _DIR_IMG_PACK . $img;
	else { // sinon, le path a ete fourni
		$f = $img;
		// gerer quand meme le cas des hacks pre 1.9.2 ou l'on faisait un path relatif depuis img_pack
		if (substr($f,0,strlen("../"._DIR_PLUGINS))=="../"._DIR_PLUGINS)
			$f = substr($img,3); // on enleve le ../ qui ne faisait que ramener au rep courant
	}
	
	if ($wrapper_state==NULL){
		global $browser_name;
		if (!strlen($browser_name)){include_spip('inc/layer');}
		$wrapper_state = ($browser_name=="MSIE");
	}
	if ($wrapper_state){
		if (!isset($wrapper_table[$d=dirname($f)])) {
			$wrapper_table[$d] = false;
			if (file_exists("$d/wrapper.php"))
				$wrapper_table[$d] = "$d/wrapper.php?file=";
		}
		if ($wrapper_table[$d])
			$f = $wrapper_table[$d] . urlencode(basename($img));
	}
	return $f;
}
// http://doc.spip.org/@http_img_pack
function http_img_pack($img, $alt, $att, $title='') {
	return "<img src='" . http_wrapper($img)
	  . ("'\nalt=\"" .
	     ($alt ? str_replace('"','',$alt) : ($title ? $title : ereg_replace('\..*$','',$img)))
	     . '" ')
	  . ($title ? " title=\"$title\"" : '')
	  . $att . " />";
}

// http://doc.spip.org/@http_href_img
function http_href_img($href, $img, $att, $title='', $style='', $class='', $evt='') {
	return  http_href($href, http_img_pack($img, $title, $att), $title, $style, $class, $evt);
}


// http://doc.spip.org/@http_style_background
function http_style_background($img, $att='')
{
  return " style='background: url(\"".http_wrapper($img)."\")" .
	    ($att ? (' ' . $att) : '') . ";'";
}

// Pour les formulaires en methode POST,
// mettre les arguments a la fois en input-hidden et dans le champ action:
// 1) on peut ainsi memoriser le signet comme si c'etait un GET
// 2) ca suit http://en.wikipedia.org/wiki/Representational_State_Transfer

// Attention: generer_url_ecrire peut rajouter des args

// http://doc.spip.org/@generer_url_post_ecrire
function generer_url_post_ecrire($script, $args='', $name='', $ancre='', $onchange='') {
	include_spip('inc/filtres');
	$action = generer_url_ecrire($script, $args);
	if ($name) $name = " name='$name'";
	return "\n<form action='$action$ancre'$name method='post'$onchange>"
	.form_hidden($action);
}

?>
