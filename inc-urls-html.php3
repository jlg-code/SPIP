<?php

// executer une seule fois
if (defined("_INC_URLS2")) return;
define("_INC_URLS2", "1");

function generer_url_article($id_article) {
	$url = "article$id_article.html";
	if ($GLOBALS['activer_url_recherche'] && $GLOBALS['recherche']) $url .= "?var_recherche=".urlencode($GLOBALS['recherche']);
	return $url;
}

function generer_url_rubrique($id_rubrique) {
	$url = "rubrique$id_rubrique.html";
	if ($GLOBALS['activer_url_recherche'] && $GLOBALS['recherche']) $url .= "?var_recherche=".urlencode($GLOBALS['recherche']);
	return $url;
}

function generer_url_breve($id_breve) {
	$url = "breve$id_breve.html";
	if ($GLOBALS['activer_url_recherche'] && $GLOBALS['recherche']) $url .= "?var_recherche=".urlencode($GLOBALS['recherche']);
	return $url;
}

function generer_url_forum($id_forum) {
	$url = "forum$id_forum.html";
	if ($GLOBALS['activer_url_recherche'] && $GLOBALS['recherche']) $url .= "?var_recherche=".urlencode($GLOBALS['recherche']);
	return $url;
}

function generer_url_mot($id_mot) {
	$url = "mot$id_mot.html";
	if ($GLOBALS['activer_url_recherche'] && $GLOBALS['recherche']) $url .= "?var_recherche=".urlencode($GLOBALS['recherche']);
	return $url;
}

function generer_url_auteur($id_auteur) {
	$url = "auteur$id_auteur.html";
	if ($GLOBALS['activer_url_recherche'] && $GLOBALS['recherche']) $url .= "?var_recherche=".urlencode($GLOBALS['recherche']);
	return $url;
}

function generer_url_document($id_document) {
	if ($id_document > 0) {
		$query = "SELECT fichier FROM spip_documents WHERE id_document = $id_document";
		$result = spip_query($query);
		if ($row = spip_fetch_array($result)) {
			$url = $row['fichier'];
		}
	}
	return $url;
}

function recuperer_parametres_url($fond, $url) {
	global $contexte;
	return;
}


?>