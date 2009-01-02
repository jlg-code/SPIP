<?php

/***************************************************************************\
 *  SPIP, Systeme de publication pour l'internet                           *
 *                                                                         *
 *  Copyright (c) 2001-2009                                                *
 *  Arnaud Martin, Antoine Pitrou, Philippe Riviere, Emmanuel Saint-James  *
 *                                                                         *
 *  Ce programme est un logiciel libre distribue sous licence GNU/GPL.     *
 *  Pour plus de details voir le fichier COPYING.txt ou l'aide en ligne.   *
\***************************************************************************/

if (!defined("_ECRIRE_INC_VERSION")) return;

//
// Fonction des balises #LOGO_XXXX
// (les balises portant ce type de nom sont traitees en bloc ici)
//

// http://doc.spip.org/@balise_LOGO__dist
function balise_LOGO__dist ($p) {

	preg_match(",^LOGO_([A-Z]+)(_.*)?$,i", $p->nom_champ, $regs);
	$type = strtolower($regs[1]);
	$suite_logo = @$regs[2];	

	// cas de #LOGO_SITE_SPIP
	if (preg_match(",^_SPIP(.*)$,", $suite_logo, $regs)) {
		$type = 'site';
		$suite_logo = $regs[1];
		$_id_objet = "\"'0'\"";
		$id_objet = 'id_syndic'; # parait faux mais donne bien "siteNN"
	} else {
		if ($type == 'site')
			$id_objet = "id_syndic";
		else
			$id_objet = "id_". $type;
		$_id_objet = champ_sql($id_objet, $p);
	}

	// analyser les faux filtres
	$fichier = 0;
	$align = $lien = $params = '';

	if (is_array($p->fonctions)) {
		foreach($p->fonctions as $couple) {
			$nom = trim($couple[0]);

			// double || signifie "on passe aux vrais filtres"
			if ($nom == '') {
				if ($couple[1]) {
					$params = $couple[1]; // recuperer #LOGO_DOCUMENT{20,30}
					array_shift($p->param);
				} else break;
			} else {
				array_shift($p->param);
				$r = logo_faux_filtres($nom);
				if ($r === 0)
					$align = $nom;
				else {
					if ($r === 2)
						$fichier = -1;
					elseif ($r === 1)
						$lien = ' ';
					else	$lien = $nom;
					break;
				}
			}
		}
	}

	if ($lien) {
		if (preg_match(",^[^#]*#([A-Za-z_]+),", $lien, $r)) {
			$c = new Champ();
			$c->nom_champ = $r[1];
			$c->id_boucle = $p->id_boucle;
			$c->boucles = &$p->boucles;
			$c->descr = $p->descr;
			$lien = calculer_champ($c);
		} else {
			include_spip('balise/url_');
			$lien = generer_generer_url_arg($type, $p, $_id_objet);
		}
	}

	$connect = $p->id_boucle ?$p->boucles[$p->id_boucle]->sql_serveur :'';
	if ($type == 'document') {
		if (preg_match('/{\s*(\d+),\s*(\d+)\s*}/', $params, $r)) {
			$x = intval($r[1]);
			$y = intval($r[2]);
		} else $x = $y = 0;

		$qconnect = _q($connect);
		if ($fichier)
			$code = "quete_logo_file(quete_document($_id_objet, $qconnect), $qconnect)";
		else $code = "quete_logo_document(quete_document($_id_objet, $qconnect), " . ($lien ? $lien : "''") . ", '$align', $x, $y, $qconnect)";
		// (x=non-faux ? y : '') pour affecter x en retournant y
		if ($p->descr['documents'])
		  $code = '(($doublons["documents"] .= ",". '
		    . $_id_objet
		    . ") ? $code : '')";
	}
	elseif ($connect) {
		$code = "''";
		spip_log("Les logos distants ne sont pas prevus");
	} else {
		$code = logo_survol($id_objet, $_id_objet, $type, $align, $fichier, $lien, $p, $suite_logo);
	}
	$p->code = $code;
	$p->interdire_scripts = false;
	return $p;
}

function logo_survol($id_objet, $_id_objet, $type, $align, $fichier, $lien, $p, $suite)
{
	$code = "quete_logo('$id_objet', '" .
		(($suite == '_SURVOL') ? 'off' : 
		(($suite == '_NORMAL') ? 'on' : 'ON')) .
		"', $_id_objet," .
		(($suite == '_RUBRIQUE') ? 
		champ_sql("id_rubrique", $p) :
		(($type == 'rubrique') ? "quete_parent($_id_objet)" : "''")) .
		", " . intval($fichier) . ")";

	if ($fichier) return $code;

	$code = "\n((!is_array(\$l = $code)) ? '':\n (" .
		     '"<img class=\"spip_logos\" alt=\"\"' .
		    ($align ? " align=\"$align\"" : '')
		    . ' src=\"$l[0]\"" . $l[3] .  ($l[1] ? " onmouseover=\"this.src=\'$l[1]\'\" onmouseout=\"this.src=\'$l[0]\'\"" : "") . \' />\'))';

	if (!$lien) return $code;

	return ('\'<a href="\' .' . $lien . ' . \'"> \' . ' . $code . " . '</a>'");

}// Pour les documents comme pour les logos, le filtre |fichier donne
// le chemin du fichier apres 'IMG/' ;  peut-etre pas d'une purete
// remarquable, mais a conserver pour compatibilite ascendante.
// -> http://www.spip.net/fr_article901.html

function logo_faux_filtres($nom)
{
	switch($nom) {
	case 'top':
	case 'left':
	case 'right':
	case 'center':
	case 'bottom':  return 0;
	case 'lien':    return 1;
	case 'fichier': return 2;
	default: return $nom;
	}
}

?>
