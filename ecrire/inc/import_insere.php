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

// http://doc.spip.org/@insere_2_init
function insere_2_init($request) {

	// l'insertion porte sur les tables principales ...
	$t = array_keys($GLOBALS['tables_principales']);
	// ... mais pas cette table car elle n'est pas extensible ..
	// (si on essaye ==> duplication sur la cle secondaire)
	unset($t[array_search('spip_types_documents', $t)]);
	// .. ni celle-ci a cause de la duplication des login 
	unset($t[array_search('spip_auteurs', $t)]);
	// et les tables auxiliaires sur les mots car on sait les identifier
	$t[]= 'spip_mots_articles';
	$t[]= 'spip_mots_breves';
	$t[]= 'spip_mots_rubriques';
	$t[]= 'spip_mots_syndic';
	$t[]= 'spip_mots_forum';
	$t[]= 'spip_mots_documents';

	return $t;
}

// http://doc.spip.org/@insere_1_init
function insere_1_init($request) {

  //  preparation de la table des translations
	$spip_translate = array(
		"type"		=> "VARCHAR(16) NOT NULL",
		"ajout"		=> "ENUM('0', '1')",
		"titre"		=> "text NOT NULL",
                "id_old"	=> "BIGINT (21) DEFAULT '0' NOT NULL",
                "id_new"	=> "BIGINT (21) DEFAULT '0' NOT NULL");

	$spip_translate_key = array(
                "PRIMARY KEY"	=> "id_old, id_new, type",
                "KEY id_old"	=> "id_old");

	include_spip('base/create');
	spip_create_table('spip_translate', $spip_translate, $spip_translate_key, true);
	// au cas ou la derniere fois ce serait terminee anormalement
	spip_query("DELETE FROM spip_translate");
	return insere_2_init($request);
}

// http://doc.spip.org/@translate_init
function translate_init($request) {
  /* 
   construire le tableau PHP de la table spip_translate
   (mis en table pour pouvoir reprendre apres interruption)
  */
	$q = spip_query("SELECT * FROM spip_translate");
	$trans = array();
	while ($r = spip_fetch_array($q)) {
		$trans[$r['type']][$r['id_old']] = array($r['id_new'], $r['titre'], intval($r['ajout']));
	}
	return $trans;
}


// http://doc.spip.org/@import_insere
function import_insere($values, $table, $desc, $request, $trans) {
	$type_id = $desc['key']["PRIMARY KEY"];

	// reserver une place dans les tables principales si nouveau
	$ajout = 0;
	if ((!function_exists($f = 'import_identifie_' . $type_id))
	OR (!$n = $f($values, $table, $desc, $request, $trans))) {
		$n = spip_abstract_insert($table, '', '()');
		if (!$n) {
			$GLOBALS['erreur_restauration'] = spip_sql_error();
			return;
		}
		$ajout = 1;
	}
	// et memoriser la correspondance dans la table auxiliaire
	// si different et pas de recherche dessus plus tard
	if (($n != $values[$type_id]) OR $table != 'id_groupe') {
		if (is_array($n))
		  list($id, $titre) = $n; 
		else {$id = $n; $titre = "";}
		spip_abstract_insert('spip_translate',
				"(id_old, id_new, titre, type, ajout)",
				     "(". $values[$type_id] .",$id, " . _q($titre) . ", '$type_id', '$ajout')");
	}
}

// http://doc.spip.org/@import_translate
function import_translate($values, $table, $desc, $request, $trans) {
	$vals = '';

	foreach ($values as $k => $v) {
		if ($k=='id_parent' OR $k=='id_secteur') $k = 'id_rubrique';

		if (isset($trans[$k]) AND isset($trans[$k][$v])) {
			list($g, $titre, $ajout) = $trans[$k][$v];
			if ($g < 0) {
			  // cas du  parent a verifier en plus du titre
			  // pour l'instant il n'y a que spip_mots
				if (!($g = import_identifie_mot_si_groupe(0-$g, $titre, $trans))) {
					$g = spip_abstract_insert('spip_mots', '', '()');
					$trans[$k][$v][2]=1;
					
				}
			// Memoriser le nouveau numero pour ne pas recalculer
			// Mise a jour de spip_translate pas indispensable,
			// on evite: si vraiment une interrupt a lieu et
			// retombe dessus, elle recalculera, pas dramatique
				$trans[$k][$v][0] = $g;
			}
			$v = $g;
		}

		$vals .= "," . _q($v);
	}
	// si le champ titre est la, c'est une equivalence avec un existant
	// pas la peine d'ecraser avec les memes valerurs
	$p = $desc['key']["PRIMARY KEY"];
	$v = $values[$p];
	if (!isset($trans[$p]) OR !isset($trans[$p][$v]) OR $trans[$p][$v][2])
		spip_query("REPLACE $table (" . join(',',array_keys($values)) . ') VALUES (' .substr($vals,1) . ')');
}


// deux groupes de mots ne peuvent avoir le meme titre ==> identification
function import_identifie_id_groupe($values, $table, $desc, $request, $trans) {
	$r = spip_fetch_array(spip_query($q = "SELECT id_groupe, titre FROM spip_groupes_mots WHERE titre=" . _q($values['titre'])), SPIP_NUM);
	return $r;
}

// pour un mot le titre est insuffisant, il faut aussi l'identite du groupe.
// Memoriser ces 2 infos et le signaler a import_translate grace a 1 negatif
function import_identifie_id_mot($values, $table, $desc, $request, $trans) {
	return array((0 - $values['id_groupe']), $values['titre']);
}

// mot de meme titre et de meme groupe ==> identification
function import_identifie_mot_si_groupe($id_groupe, $titre, $trans)
{
	if (!(isset($trans['id_groupe'])
	AND isset($trans['id_groupe'][$id_groupe])))
		return false;

	$new = $trans['id_groupe'][$id_groupe][0];

	$r = spip_fetch_array(spip_query("SELECT id_mot FROM spip_mots WHERE titre=" . _q($titre) . " AND id_groupe=$new" ));

	return !$r ? false  : $r['id_mot'];
}
?>

