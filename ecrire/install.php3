<?php
if (defined("_ECRIRE_INSTALL")) return;
define("_ECRIRE_INSTALL", "1");

include ("inc_version.php3");
include_ecrire ("inc_presentation.php3");


// Une fonction pour faciliter la recherche du login (superflu ?)
function login_hebergeur() {
	global $HTTP_X_HOST, $REQUEST_URI, $SERVER_NAME, $HTTP_HOST;

	// Lycos (ex-Multimachin)
	if ($HTTP_X_HOST == 'membres.lycos.fr') {
		ereg('^/([^/]*)', $REQUEST_URI, $regs);
		$login_hebergeur = $regs[1];
	}
	// Altern
	else if (ereg('altern\.com$', $SERVER_NAME)) {
		ereg('([^.]*\.[^.]*)$', $HTTP_HOST, $regs);
		$login_hebergeur = ereg_replace('[^a-zA-Z0-9]', '_', $regs[1]);
	}
	// Free
	else if (ereg('^/([^/]*)\.free.fr/', $REQUEST_URI, $regs)) {
		$login_hebergeur = $regs[1];
	}

	return $login_hebergeur;
}



if (_FILE_CONNECT) {
	install_debut_html();
	echo "<P><FONT FACE='Verdana,Arial,Sans,sans-serif' SIZE=4>"._T('avis_espace_interdit')."</FONT>";

	install_fin_html();
	exit;
}

include_ecrire ("inc_base.php3");
define('_FILE_TMP', '_install');

//
// Etapes de l'installation standard
//

if ($etape == 6) {
	install_debut_html();

	echo "<BR><FONT FACE='Verdana,Arial,Sans,sans-serif' SIZE=3>"._T('info_derniere_etape')."</B></FONT>";
	echo "<P>";
	echo "<B>"._T('info_code_acces')."</B>";
	echo "<P>"._T('info_utilisation_spip');

	include(_FILE_CONNECT_INS . _FILE_TMP . _EXTENSION_PHP);
	include_ecrire ("inc_meta.php3");

	if ($login) {
		$nom = addslashes($nom);
		$login = addslashes($login);
		$query = "SELECT id_auteur FROM spip_auteurs WHERE login='$login'";
		$result = spip_query_db($query);
		unset($id_auteur);
		while ($row = spip_fetch_array($result)) $id_auteur = $row['id_auteur'];

		$mdpass = md5($pass);
		$htpass = generer_htpass($pass);

		if ($id_auteur) {
			$query = "UPDATE spip_auteurs SET nom='$nom', email='$email', login='$login', pass='$mdpass', alea_actuel='', alea_futur=FLOOR(32000*RAND()), htpass='$htpass', statut='0minirezo' WHERE id_auteur=$id_auteur";
		}
		else {
			$query = "INSERT INTO spip_auteurs (nom, email, login, pass, htpass, alea_futur, statut) VALUES('$nom','$email','$login','$mdpass','$htpass',FLOOR(32000*RAND()),'0minirezo')";
		}
		spip_query_db($query);

		// inserer email comme email webmaster principal
		spip_query_db("REPLACE spip_meta (nom, valeur)
			VALUES ('email_webmaster', '".addslashes($email)."')");
	}

	include_ecrire("inc_config.php3");
	init_config();
	init_langues();

	include_ecrire ("inc_acces.php3");
	ecrire_acces();

	@unlink(_DIR_SESSIONS . 'inc_meta_cache.php3');
	@unlink(_DIR_SESSIONS . 'meta_cache.php3');
	if (!@rename(_FILE_CONNECT_INS . _FILE_TMP . _EXTENSION_PHP,
		    _FILE_CONNECT_INS . _EXTENSION_PHP)) {
		copy(_FILE_CONNECT_INS . _FILE_TMP . _EXTENSION_PHP, 
		     _FILE_CONNECT_INS . _EXTENSION_PHP);
		@unlink(_FILE_CONNECT_INS . _FILE_TMP . _EXTENSION_PHP);
	}

	echo "<FORM ACTION='index.php3' METHOD='post'>";
	echo "<DIV align='$spip_lang_right'><INPUT TYPE='submit' CLASS='fondl'  VALUE='"._T('bouton_suivant')." >>'>";
	echo "</FORM>";

	ecrire_metas();

	install_fin_html();
}

else if ($etape == 5) {
	install_debut_html();

	include(_FILE_CONNECT_INS . _FILE_TMP . _EXTENSION_PHP);

	echo "<BR />\n<FONT FACE='Verdana,Arial,Sans,sans-serif' SIZE=3>"._T('info_informations_personnelles')."</FONT>";
	echo "<P>\n";

	echo "<b>"._T('texte_informations_personnelles_1')."</b>";
	echo aide ("install5");
	echo "<p>\n"._T('texte_informations_personnelles_2')." ";
	echo _T('info_laisser_champs_vides');

	echo "<FORM ACTION='install.php3' METHOD='post'>";
	echo "<INPUT TYPE='hidden' NAME='etape' VALUE='6'>";

	echo "<fieldset><label><B>"._T('info_identification_publique')."</B><BR />\n</label>";
	echo "<B>"._T('entree_signature')."</B><BR />\n";
	echo _T('entree_nom_pseudo_1')."<BR />\n";
	echo "<INPUT TYPE='text' NAME='nom' CLASS='formo' VALUE=\"$nom\" SIZE='40'><P>\n";

	echo "<B>"._T('entree_adresse_email')."</B><BR />\n";
	echo "<INPUT TYPE='text' NAME='email' CLASS='formo' VALUE=\"$email\" SIZE='40'></fieldset><P>\n";

	echo "<fieldset><label><B>"._T('entree_identifiants_connexion')."</B><BR />\n</label>";
	echo "<B>"._T('entree_login')."</B><BR />\n";
	echo _T('info_plus_trois_car')."<BR />\n";
	echo "<INPUT TYPE='text' NAME='login' CLASS='formo' VALUE=\"$login\" SIZE='40'><P>\n";

	echo "<B>"._T('entree_mot_passe')."</B> <BR />\n";
	echo _T('info_plus_cinq_car_2')."<BR />\n";
	echo "<INPUT TYPE='password' NAME='pass' CLASS='formo' VALUE=\"$pass\" SIZE='40'></fieldset><P>\n";

	echo "<DIV align='$spip_lang_right'><INPUT TYPE='submit' CLASS='fondl'  VALUE='"._T('bouton_suivant')." >>'>";
	echo "</FORM>";
	echo "<p>\n";

	if ($flag_ldap AND !$ldap_present) {
		echo "<div style='border: 1px solid #404040; padding: 10px; text-align: left;'>";
		echo "<b>"._T('info_authentification_externe')."</b>";
		echo "<p>\n"._T('texte_annuaire_ldap_1');
		echo "<FORM ACTION='install.php3' METHOD='post'>";
		echo "<INPUT TYPE='hidden' NAME='etape' VALUE='ldap1'>";
		echo "<DIV align='$spip_lang_right'><INPUT TYPE='submit' CLASS='fondl'  VALUE=\""._T('bouton_acces_ldap')."\">";
		echo "</FORM>";
	}

	install_fin_html();

}

else if ($etape == 4) {

	install_debut_html();

	// Necessaire pour appeler les fonctions SQL wrappees
	include_ecrire("inc_db_mysql.php3");

	echo "<BR />\n<FONT FACE='Verdana,Arial,Sans,sans-serif' SIZE=3>"._T('info_creation_tables')."</FONT>";
	echo "<P>\n";

	$link = mysql_connect("$adresse_db", "$login_db", "$pass_db");

	echo "<"."!-- $link ";

	if ($choix_db == "new_spip") {
		$sel_db = $table_new;
		mysql_query("CREATE DATABASE $sel_db");
	}
	else {
		$sel_db = $choix_db;
	}
	echo $sel_db;
	mysql_select_db("$sel_db");

	// Test si SPIP deja installe
	@spip_query_db("SELECT COUNT(*) FROM spip_meta");
	$nouvelle = spip_sql_errno();

	creer_base();
	$maj_ok = maj_base();

	if ($nouvelle) {
		spip_query_db("INSERT spip_meta (nom, valeur) VALUES ('nouvelle_install', 'oui')");
		$result_ok = !spip_sql_errno();
	} else {
		$result = spip_query_db("SELECT COUNT(*) FROM spip_articles");
		$result_ok = (spip_num_rows($result) > 0);
	}
	echo "($result_ok && $maj_ok) -->";


	if ($result_ok && $maj_ok) {
		$conn = "<"."?php\n";
		$conn .= "if (defined(\"_ECRIRE_INC_CONNECT\")) return;\n";
		$conn .= "define(\"_ECRIRE_INC_CONNECT\", \"1\");\n";
		$conn .= "\$GLOBALS['spip_connect_version'] = 0.1;\n";
		$conn .= "include_ecrire('inc_db_mysql.php3');\n";
		$conn .= "@spip_connect_db('$adresse_db','','$login_db','$pass_db','$sel_db');\n";
		$conn .= "\$GLOBALS['db_ok'] = !!@spip_num_rows(@spip_query_db('SELECT COUNT(*) FROM spip_meta'));\n";
		$conn .= "?".">";
		$myFile = fopen(_FILE_CONNECT_INS . _FILE_TMP . _EXTENSION_PHP, "wb");
		fputs($myFile, $conn);
		fclose($myFile);

		echo "<B>"._T('info_base_installee')."</B><P>\n"._T('info_etape_suivante_1');

		echo "<FORM ACTION='install.php3' METHOD='post'>";
		echo "<INPUT TYPE='hidden' NAME='etape' VALUE='5'>";

		echo "<DIV align='$spip_lang_right'><INPUT TYPE='submit' CLASS='fondl'  VALUE='"._T('bouton_suivant')." >>'>";

		echo "</FORM>";
	}
	else if ($result_ok) {
		echo _T('alerte_maj_impossible', array('version' => $spip_version));
	}
	else {
		echo "<B>"._T('avis_operation_echec')."</B> "._T('texte_operation_echec');
	}

	install_fin_html();

}

else if ($etape == 3) {

	install_debut_html();

	echo "<BR />\n<FONT FACE='Verdana,Arial,Sans,sans-serif' SIZE=3>"._T('info_choix_base')." <B>"._T('menu_aide_installation_choix_base')."</B></FONT>";

	echo aide ("install2");
	echo "<P>\n";

	echo "<FORM ACTION='install.php3' METHOD='post'>";
	echo "<INPUT TYPE='hidden' NAME='etape' VALUE='4'>";
	echo "<INPUT TYPE='hidden' NAME='adresse_db'  VALUE=\"$adresse_db\" SIZE='40'>";
	echo "<INPUT TYPE='hidden' NAME='login_db' VALUE=\"$login_db\">";
	echo "<INPUT TYPE='hidden' NAME='pass_db' VALUE=\"$pass_db\"><P>\n";

	$link = mysql_connect("$adresse_db","$login_db","$pass_db");
	$result = @mysql_list_dbs();

	echo "<fieldset><label><B>"._T('texte_choix_base_1')."</B><BR />\n</label>";

	if ($result AND (($n = @mysql_num_rows($result)) > 0)) {
		echo "<B>"._T('texte_choix_base_2')."</B><P> "._T('texte_choix_base_3');
		echo "<UL>";
		$bases = "";
		for ($i = 0; $i < $n; $i++) {
			$table_nom = mysql_dbname($result, $i);
			$base = "<INPUT NAME=\"choix_db\" VALUE=\"".$table_nom."\" TYPE=Radio id='tab$i'";
			$base_fin = "><label for='tab$i'>".$table_nom."</label><BR />\n\n";
			if ($table_nom == $login_db) {
				$bases = "$base CHECKED$base_fin".$bases;
				$checked = true;
			}
			else {
				$bases .= "$base$base_fin\n";
			}
		}
		echo $bases."</UL>";
		echo _T('info_ou')." ";
	}
	else {
		echo "<B>"._T('avis_lecture_noms_bases_1')."</B>
		"._T('avis_lecture_noms_bases_2')."<P>";
		if ($login_db) {
			echo _T('avis_lecture_noms_bases_3');
			echo "<UL>";
			echo "<INPUT NAME=\"choix_db\" VALUE=\"".$login_db."\" TYPE=Radio id='stand' CHECKED>";
			echo "<label for='stand'>".$login_db."</label><BR />\n";
			echo "</UL>";
			echo _T('info_ou')." ";
			$checked = true;
		}
	}
	echo "<INPUT NAME=\"choix_db\" VALUE=\"new_spip\" TYPE=Radio id='nou'";
	if (!$checked) echo " CHECKED";
	echo "> <label for='nou'>"._T('info_creer_base')."</label> ";
	echo "<INPUT TYPE='text' NAME='table_new' CLASS='fondo' VALUE=\"spip\" SIZE='20'></fieldset><P>";

	echo "<DIV align='$spip_lang_right'><INPUT TYPE='submit' CLASS='fondl'  VALUE='"._T('bouton_suivant')." >>'>";


	echo "</FORM>";

	install_fin_html();

}

else if ($etape == 2) {

	install_debut_html();

	echo "<BR />\n<FONT FACE='Verdana,Arial,Sans,sans-serif' SIZE=3>"._T('info_connexion_base')."</FONT>";

	echo "<!--";
	$link = mysql_connect("$adresse_db","$login_db","$pass_db");
	$db_connect = mysql_errno();
	echo "-->";

	echo "<P>";

	if (($db_connect=="0") && $link){
		echo "<B>"._T('info_connexion_ok')."</B><P> "._T('info_etape_suivante_2');

		echo "<FORM ACTION='install.php3' METHOD='post'>";
		echo "<INPUT TYPE='hidden' NAME='etape' VALUE='3'>";
		echo "<INPUT TYPE='hidden' NAME='adresse_db'  VALUE=\"$adresse_db\" SIZE='40'>";
		echo "<INPUT TYPE='hidden' NAME='login_db' VALUE=\"$login_db\">";
		echo "<INPUT TYPE='hidden' NAME='pass_db' VALUE=\"$pass_db\"><P>";

		echo "<DIV align='$spip_lang_right'><INPUT TYPE='submit' CLASS='fondl'  VALUE='"._T('bouton_suivant')." >>'>";
		echo "</FORM>";
	}
	else {
		echo "<B>"._T('avis_connexion_echec_1')."</B>";
		echo "<P>"._T('avis_connexion_echec_2');
		echo "<P><FONT SIZE=2>"._T('avis_connexion_echec_3')."</FONT>";
	}

	install_fin_html();

}
else if ($etape == 1) {
	install_debut_html();

	echo "<BR />\n<FONT FACE='Verdana,Arial,Sans,sans-serif' SIZE=3>"._T('info_connexion_mysql')."</FONT>";

	echo "<P>"._T('texte_connexion_mysql');

	echo aide ("install1");

	$adresse_db = 'localhost';
	$login_db = login_hebergeur();
	$pass_db = '';

	// Recuperer les anciennes donnees pour plus de facilite (si presentes)
	if (@file_exists(_FILE_CONNECT_INS . _FILE_TMP . _EXTENSION_PHP)) {
		$s = @join('', @file(_FILE_CONNECT_INS . _FILE_TMP . _EXTENSION_PHP));
		if (ereg("mysql_connect\([\"'](.*)[\"'],[\"'](.*)[\"'],[\"'](.*)[\"']\)", $s, $regs)) {
			$adresse_db = $regs[1];
			$login_db = $regs[2];
		}
		else if (ereg("spip_connect_db\('(.*)','(.*)','(.*)','(.*)','(.*)'\)", $s, $regs)) {
			$adresse_db = $regs[1];
			if ($port_db = $regs[2]) $adresse_db .= ':'.$port_db;
			$login_db = $regs[3];
		}
	}

	echo "<p><FORM ACTION='install.php3' METHOD='post'>";
	echo "<INPUT TYPE='hidden' NAME='etape' VALUE='2'>";
	echo "<fieldset><label><B>"._T('entree_base_donnee_1')."</B><BR />\n</label>";
	echo _T('entree_base_donnee_2')."<BR />\n";
	echo "<INPUT TYPE='text' NAME='adresse_db' CLASS='formo' VALUE=\"$adresse_db\" SIZE='40'></fieldset><P>";

	echo "<fieldset><label><B>"._T('entree_login_connexion_1')."</B><BR />\n</label>";
	echo _T('entree_login_connexion_2')."<BR />\n";
	echo "<INPUT TYPE='text' NAME='login_db' CLASS='formo' VALUE=\"$login_db\" SIZE='40'></fieldset><P>";

	echo "<fieldset><label><B>"._T('entree_mot_passe_1')."</B><BR />\n</label>";
	echo _T('entree_mot_passe_2')."<BR />\n";
	echo "<INPUT TYPE='password' NAME='pass_db' CLASS='formo' VALUE=\"$pass_db\" SIZE='40'></fieldset><P>";

	echo "<DIV align='$spip_lang_right'><INPUT TYPE='submit' CLASS='fondl'  VALUE='"._T('bouton_suivant')." >>'>";


	echo "</FORM>";

	install_fin_html();

}
else if ($etape == 'dirs') {
	header("Location: ../spip_test_dirs.php3");
}
else if (!$etape) {
	$menu_langues = menu_langues('var_lang_ecrire');

	if (!$menu_langues)
		header("Location: ../spip_test_dirs.php3");
	else {
		install_debut_html();

		echo "<p align='center'><img src='" . _DIR_IMG_PACK . "logo-spip.gif'></p>";
		
		echo "<p style='text-align: center; font-family: Verdana,Arial,Sans,sans-serif; font-size: 10px;'>";
		echo "<b>SPIP $spip_version_affichee</b> ";
		echo _T('info_copyright');

		echo "</p>";

		echo "<p>" . _T('install_select_langue');

		echo "<p><div align='center'>".$menu_langues."</div>";

		echo "<p><FORM ACTION='install.php3' METHOD='get'>";
		echo "<INPUT TYPE='hidden' NAME='etape' VALUE='dirs'>";
		echo "<DIV align='$spip_lang_right'><INPUT TYPE='submit' CLASS='fondl'  VALUE='"._T('bouton_suivant')." >>'>";
		echo "</FORM>";
		install_fin_html();
	}
}


//
// Etapes de l'installation LDAP
//

else if ($etape == 'ldap5') {
	install_debut_html();

	include_local(_FILE_CONNECT_INS . _FILE_TMP . _EXTENSION_PHP);
	include_ecrire('inc_meta.php3');
	ecrire_meta("ldap_statut_import", $statut_ldap);
	ecrire_metas();

	echo "<B>"._T('info_ldap_ok')."</B>";
	echo "<P>"._T('info_terminer_installation');

	echo "<FORM ACTION='install.php3' METHOD='post'>";
	echo "<INPUT TYPE='hidden' NAME='etape' VALUE='5'>";

	echo "<DIV align='$spip_lang_right'><INPUT TYPE='submit' CLASS='fondl'  VALUE='"._T('bouton_suivant')." >>'>";

	echo "</FORM>";
}

else if ($etape == 'ldap4') {
	install_debut_html();

	if (!$base_ldap) $base_ldap = $base_ldap_text;

	$ldap_link = @ldap_connect("$adresse_ldap", "$port_ldap");
	@ldap_bind($ldap_link, "$login_ldap", "$pass_ldap");

	// Essayer de verifier le chemin fourni
	$r = @ldap_compare($ldap_link, $base_ldap, "objectClass", "");
	$fail = (ldap_errno($ldap_link) == 32);

	if ($fail) {
		echo "<BR />\n<FONT FACE='Verdana,Arial,Sans,sans-serif' SIZE=3>"._T('info_chemin_acces_annuaire')."</B></FONT>";
		echo "<P>";

		echo "<B>"._T('avis_operation_echec')."</B> "._T('avis_chemin_invalide_1')." (<tt>".htmlspecialchars($base_ldap);
		echo "</tt>) "._T('avis_chemin_invalide_2');
	}
	else {
		echo "<BR />\n<FONT FACE='Verdana,Arial,Sans,sans-serif' SIZE=3>"._T('info_reglage_ldap')."</FONT>";
		echo "<P>";

		$conn = join('', file(_FILE_CONNECT_INS . _FILE_TMP . _EXTENSION_PHP));
		if ($p = strpos($conn, '?'.'>')) 
			$conn = substr($conn, 0, $p);
		if (!strpos($conn, 'spip_connect_ldap')) {
			$conn .= "function spip_connect_ldap() {\n";
			$conn .= "\t\$GLOBALS['ldap_link'] = @ldap_connect(\"$adresse_ldap\",\"$port_ldap\");\n";
			$conn .= "\t@ldap_bind(\$GLOBALS['ldap_link'],\"$login_ldap\",\"$pass_ldap\");\n";
			$conn .= "\treturn \$GLOBALS['ldap_link'];\n";
			$conn .= "}\n";
			$conn .= "\$GLOBALS['ldap_base'] = \"$base_ldap\";\n";
			$conn .= "\$GLOBALS['ldap_present'] = true;\n";
		}
		$conn .= "?".">";
		$myFile = fopen(_FILE_CONNECT_INS . _FILE_TMP . _EXTENSION_PHP, "wb");
		fputs($myFile, $conn);
		fclose($myFile);

		echo "<p><FORM ACTION='install.php3' METHOD='post'>";
		echo "<INPUT TYPE='hidden' NAME='etape' VALUE='ldap5'>";
		echo "<fieldset><label><B>"._T('info_statut_utilisateurs_1')."</B></label><BR />\n";
		echo _T('info_statut_utilisateurs_2')." ";
		echo "<p>";
		echo "<INPUT TYPE='Radio' NAME='statut_ldap' VALUE=\"6forum\" id='visit'>";
		echo "<label for='visit'><b>"._T('info_visiteur_1')."</b></label> "._T('info_visiteur_2')."<br />\n";
		echo "<INPUT TYPE='Radio' NAME='statut_ldap' VALUE=\"1comite\" id='redac' CHECKED>";
		echo "<label for='redac'><b>"._T('info_redacteur_1')."</b></label> "._T('info_redacteur_2')."<br />\n";
		echo "<INPUT TYPE='Radio' NAME='statut_ldap' VALUE=\"0minirezo\" id='admin'>";
		echo "<label for='admin'><b>"._T('info_administrateur_1')."</b></label> "._T('info_administrateur_2')."<br />\n";
	
		echo "<DIV align='$spip_lang_right'><INPUT TYPE='submit' CLASS='fondl'  VALUE='"._T('bouton_suivant')." >>'>";

		echo "</FORM>";
	}

	install_fin_html();
}

else if ($etape == 'ldap3') {
	install_debut_html();

	echo "<BR />\n<FONT FACE='Verdana,Arial,Sans,sans-serif' SIZE=3>"._T('info_chemin_acces_1')."</FONT>";

	echo "<P>"._T('info_chemin_acces_2');

	$ldap_link = @ldap_connect("$adresse_ldap", "$port_ldap");
	@ldap_bind($ldap_link, "$login_ldap", "$pass_ldap");

	$result = @ldap_read($ldap_link, "", "objectclass=*", array("namingContexts"));
	$info = @ldap_get_entries($ldap_link, $result);

	echo "<FORM ACTION='install.php3' METHOD='post'>";
	echo "<INPUT TYPE='hidden' NAME='etape' VALUE='ldap4'>";
	echo "<INPUT TYPE='hidden' NAME='adresse_ldap' VALUE=\"$adresse_ldap\">";
	echo "<INPUT TYPE='hidden' NAME='port_ldap' VALUE=\"$port_ldap\">";
	echo "<INPUT TYPE='hidden' NAME='login_ldap' VALUE=\"$login_ldap\">";
	echo "<INPUT TYPE='hidden' NAME='pass_ldap' VALUE=\"$pass_ldap\">";

	echo "<fieldset>";

	$checked = false;

	if (is_array($info) AND $info["count"] > 0) {
		echo "<P>"._T('info_selection_chemin_acces');
		echo "<UL>";
		$n = 0;
		for ($i = 0; $i < $info["count"]; $i++) {
			$names = $info[$i]["namingcontexts"];
			if (is_array($names)) {
				for ($j = 0; $j < $names["count"]; $j++) {
					$n++;
					echo "<INPUT NAME=\"base_ldap\" VALUE=\"".htmlspecialchars($names[$j])."\" TYPE='Radio' id='tab$n'";
					if (!$checked) {
						echo " CHECKED";
						$checked = true;
					}
					echo ">";
					echo "<label for='tab$n'>".htmlspecialchars($names[$j])."</label><BR />\n\n";
				}
			}
		}
		echo "</UL>";
		echo _T('info_ou')." ";
	}
	echo "<INPUT NAME=\"base_ldap\" VALUE=\"\" TYPE='Radio' id='manuel'";
	if (!$checked) {
		echo " CHECKED";
		$checked = true;
	}
	echo ">";
	echo "<label for='manuel'>"._T('entree_chemin_acces')."</label> ";
	echo "<INPUT TYPE='text' NAME='base_ldap_text' CLASS='formo' VALUE=\"ou=users, dc=mon-domaine, dc=com\" SIZE='40'></fieldset><P>";

	echo "<DIV align='$spip_lang_right'><INPUT TYPE='submit' CLASS='fondl'  VALUE='"._T('bouton_suivant')." >>'>";
	echo "</FORM>";

	install_fin_html();

}

else if ($etape == 'ldap2') {
	install_debut_html();

	echo "<BR />\n<FONT FACE='Verdana,Arial,Sans,sans-serif' SIZE=3>"._T('titre_connexion_ldap')."</FONT>";

	echo "<P>";

	$ldap_link = @ldap_connect("$adresse_ldap", "$port_ldap");
	$r = @ldap_bind($ldap_link, "$login_ldap", "$pass_ldap");

	if ($ldap_link && ($r || !$login_ldap)) {
		echo "<B>"._T('info_connexion_ldap_ok');

		echo "<FORM ACTION='install.php3' METHOD='post'>";
		echo "<INPUT TYPE='hidden' NAME='etape' VALUE='ldap3'>";
		echo "<INPUT TYPE='hidden' NAME='adresse_ldap' VALUE=\"$adresse_ldap\">";
		echo "<INPUT TYPE='hidden' NAME='port_ldap' VALUE=\"$port_ldap\">";
		echo "<INPUT TYPE='hidden' NAME='login_ldap' VALUE=\"$login_ldap\">";
		echo "<INPUT TYPE='hidden' NAME='pass_ldap' VALUE=\"$pass_ldap\">";

		echo "<DIV align='$spip_lang_right'><INPUT TYPE='submit' CLASS='fondl'  VALUE='"._T('bouton_suivant')." >>'>";
		echo "</FORM>";
	}
	else {
		echo "<B>"._T('avis_connexion_ldap_echec_1')."</B>";
		echo "<P>"._T('avis_connexion_ldap_echec_2');
		echo "<br />\n"._T('avis_connexion_ldap_echec_3');
	}

	install_fin_html();

}

else if ($etape == 'ldap1') {
	install_debut_html();

	echo "<BR />\n<FONT FACE='Verdana,Arial,Sans,sans-serif' SIZE=3>"._T('titre_connexion_ldap')."</FONT>";

	echo "<P>"._T('entree_informations_connexion_ldap');

	$adresse_ldap = 'localhost';
	$port_ldap = 389;

	// Recuperer les anciennes donnees (si presentes)
	if (@file_exists(_FILE_CONNECT_INS . _FILE_TMP . _EXTENSION_PHP)) {
		$s = @join('', @file(_FILE_CONNECT_INS . _FILE_TMP . _EXTENSION_PHP));
		if (ereg('ldap_connect\("(.*)","(.*)"\)', $s, $regs)) {
			$adresse_ldap = $regs[1];
			$port_ldap = $regs[2];
		}
	}

	echo "<p><FORM ACTION='install.php3' METHOD='post'>";
	echo "<INPUT TYPE='hidden' NAME='etape' VALUE='ldap2'>";
	echo "<fieldset><label><B>"._T('entree_adresse_annuaire')."</B><BR />\n</label>";
	echo _T('texte_adresse_annuaire_1')."<BR />\n";
	echo "<INPUT TYPE='text' NAME='adresse_ldap' CLASS='formo' VALUE=\"$adresse_ldap\" SIZE='20'><P>";

	echo "<label><B>"._T('entree_port_annuaire')."</B><BR />\n</label>";
	echo _T('texte_port_annuaire')."<BR />\n";
	echo "<INPUT TYPE='text' NAME='port_ldap' CLASS='formo' VALUE=\"$port_ldap\" SIZE='20'><P></fieldset>";

	echo "<p><fieldset>";
	echo _T('texte_acces_ldap_anonyme_1')." ";
	echo "<label><B>"._T('entree_login_ldap')."</B><BR />\n</label>";
	echo _T('texte_login_ldap_1')."<br />\n";
	echo "<INPUT TYPE='text' NAME='login_ldap' CLASS='formo' VALUE=\"\" SIZE='40'><P>";

	echo "<label><B>"._T('entree_passe_ldap')."</B><BR />\n</label>";
	echo "<INPUT TYPE='password' NAME='pass_ldap' CLASS='formo' VALUE=\"\" SIZE='40'></fieldset>";

	echo "<p><DIV align='$spip_lang_right'><INPUT TYPE='submit' CLASS='fondl'  VALUE='"._T('bouton_suivant')." >>'>";

	echo "</FORM>";

	install_fin_html();

}


?>
