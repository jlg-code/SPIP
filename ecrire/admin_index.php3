<?php

include ("inc.php3");

include_ecrire ("inc_admin.php3");

debut_page(_L('Moteur de recherche'), "administration", "cache");


echo "<br><br><br>";
gros_titre(_L('Moteur de recherche'));


debut_gauche();

debut_droite();

if ($connect_statut != '0minirezo' OR !$connect_toutes_rubriques) {
	echo _T('avis_non_acces_page');
	fin_page();
	exit;
}

include_ecrire('inc_index.php3');




// graphe des objets indexes
$types = array('article','auteur','breve','mot','rubrique','syndic','forum','signature');
while (list(,$type) = each($types)) {
	$table = 'spip_'.table_objet($type);
	$table_index = 'spip_index_'.table_objet($type);
	$critere = critere_indexation($type);

	// mise a jour des idx='' en fonction du contenu de la table d'indexation
	if ($mise_a_jour) {
		$vus='';
		$s = spip_query("SELECT DISTINCT(id_$type) FROM $table_index");
		while ($t = spip_fetch_array($s))
			$vus.=','.$t[0];
		if ($vus)
			spip_query("UPDATE $table SET idx='oui' WHERE id_$type IN (0$vus) AND $critere AND idx=''");
	}

	// 
	$s = spip_query("SELECT idx,COUNT(*) FROM $table WHERE $critere GROUP BY idx");
	while ($t = spip_fetch_array($s)) {
		$indexes[$type][$t[0]] = $t[1];
		$index_total[$type] += $t[1];
	}
}


debut_cadre_relief();

function jauge($couleur,$pixels) {
	echo "<img src='img_pack/jauge-$couleur.gif' height='10' width='$pixels' alt='$couleur' />";
}

echo "<table>";
reset ($types);
while (list(,$type) = each($types)) if ($index_total[$type]>0) {
				if ($ifond==0){
					$ifond=1;
					$couleur="$couleur_claire";
				}else{
					$ifond=0;
					$couleur="#FFFFFF";
				}
	echo "<TR BGCOLOR='$couleur' BACKGROUND='img_pack/rien.gif'><TD WIDTH=\"100%\">";
	echo "<IMG SRC='img_pack/rien.gif' WIDTH='".($niveau*20+1)."' HEIGHT=8 BORDER=0>";
	echo "<FONT FACE='arial,helvetica,sans-serif' SIZE=2>";	
	echo $type;
	echo "</FONT></TD><TD>";
	jauge('rouge', $a = floor(300*$indexes[$type]['non']/$index_total[$type]));
	jauge('vert', $b = ceil(300*$indexes[$type]['oui']/$index_total[$type]));
	jauge('fond', 300-$a-$b);
	echo "</TD></TR>\n";
}
echo "</table>";

fin_cadre_relief();

if ($forcer_indexation = intval($forcer_indexation))
	effectuer_une_indexation ($forcer_indexation);

echo "<a href='admin_index.php3?mise_a_jour=oui'>"._L('Cliquez ici pour mettre &agrave; jour les infos d\'indexation du site').'</a><br />';
echo "<a href='admin_index.php3?forcer_indexation=20'>"._L('Cliquez ici pour forcer l\'indexation du site').'</a><br />';


echo "<BR>";

fin_page();


?>

