<?php
/* $Id: action_rapport_tftp.php 9151 2016-02-08 01:05:04Z keyser $
===========================================
   Projet SE3
   Dispositif SE3+TFTP+Sauvegarde/Restauration/Clonage
   Stephane Boireau
   Distribué selon les termes de la licence GPL
=============================================
*/

// loading libs and init
include "entete.inc.php";
include "ldap.inc.php";
include "ihm.inc.php";
//require_once "../dhcp/dhcpd.inc.php";
include "printers.inc.php";

require("lib_action_tftp.php");

//aide
$_SESSION["pageaide"]="Le_module_Clonage_des_stations#Programmer_un_rapport";

// On active les rapports d'erreurs:
//error_reporting(E_ALL);

// Bibliothèque prototype Ajax pour afficher en décalé l'état des machines:
echo "<script type='text/javascript' src='../includes/prototype.js'></script>\n";

// CSS pour mes tableaux:
echo "<link type='text/css' rel='stylesheet' href='tftp.css' />\n";

if ((is_admin("system_is_admin",$login)=="Y")||(ldap_get_right("parc_can_clone",$login)=="Y"))
{
	// Choix des parcs:
	$parc=isset($_POST['parc']) ? $_POST['parc'] : (isset($_GET['parc']) ? $_GET['parc'] : NULL);
	// Choix des machines:
	$id_machine=isset($_POST['id_machine']) ? $_POST['id_machine'] : (isset($_GET['id_machine']) ? $_GET['id_machine'] : NULL);

	$parametrage_action=isset($_POST['parametrage_action']) ? $_POST['parametrage_action'] : (isset($_GET['parametrage_action']) ? $_GET['parametrage_action'] : NULL);

	$distrib=isset($_POST['distrib']) ? $_POST['distrib'] : "slitaz";
	$sysresccd_kernel=isset($_POST['sysresccd_kernel']) ? $_POST['sysresccd_kernel'] : "rescue32";

	// Création de la table dès que possible:
	creation_tftp_tables();

	// Paramètres SliTaz:
	/*
	$nom_image=isset($_POST['nom_image']) ? $_POST['nom_image'] : (isset($_GET['nom_image']) ? $_GET['nom_image'] : NULL);
	$src_part=isset($_POST['src_part']) ? $_POST['src_part'] : (isset($_GET['src_part']) ? $_GET['src_part'] : NULL);
	$dest_part=isset($_POST['dest_part']) ? $_POST['dest_part'] : (isset($_GET['dest_part']) ? $_GET['dest_part'] : NULL);
	*/
	$auto_reboot=isset($_POST['auto_reboot']) ? $_POST['auto_reboot'] : (isset($_GET['auto_reboot']) ? $_GET['auto_reboot'] : NULL);
	$delais_reboot=isset($_POST['delais_reboot']) ? $_POST['delais_reboot'] : (isset($_GET['delais_reboot']) ? $_GET['delais_reboot'] : NULL);

	// Paramètres concernant l'action immédiate sur les machines choisies:
	$wake=isset($_POST['wake']) ? $_POST['wake'] : (isset($_GET['wake']) ? $_GET['wake'] : "n");
	$shutdown_reboot=isset($_POST['shutdown_reboot']) ? $_POST['shutdown_reboot'] : (isset($_GET['shutdown_reboot']) ? $_GET['shutdown_reboot'] : NULL);


	echo "<h1>".gettext("Action rapport TFTP")."</h1>\n";

	$restriction_parcs="n";
	if(is_admin("system_is_admin",$login)!="Y") {
		$restriction_parcs="y";
		$tab_delegated_parcs=list_delegated_parcs($login);
		if(count($tab_delegated_parcs)==0) {
			echo "<p>Aucun parc ne vous a été délégué.</p>\n";
			include ("pdp.inc.php");
			die();
		}
	}


	$temoin_fichiers_requis="y";
	$chemin_tftpboot="/tftpboot";
	$tab_udpcast_file=array("bzImage", "rootfs.gz");
	for($loop=0;$loop<count($tab_udpcast_file);$loop++) {
		if(!file_exists($chemin_tftpboot."/".$tab_udpcast_file[$loop])) {
			echo "<span style='color:red'>".$chemin_tftpboot."/".$tab_udpcast_file[$loop]." est absent.</span><br />\n";
			echo "Effectuez le telechargement SliTaz en <a href='config_tftp.php'>Configurer le module TFTP</a><br />\n";
			$temoin_fichiers_requis="n";
		}
	}

	if($temoin_fichiers_requis=="n") {
		echo "<p style='color:red'>ABANDON&nbsp;: Un ou des fichiers requis sont manquants.</p>\n";
		include ("pdp.inc.php");
		die();
	}


	if(!isset($parc)){
		echo "<p>Cette page doit vous permettre de programmer une récupération d'informations sur les machines choisies pour connaître les partitions, les sauvegardes présentes,...</p>\n";

		echo "<p>Choisissez un ou des parcs:</p>\n";

		$list_parcs=search_machines("objectclass=groupOfNames","parcs");
		if ( count($list_parcs)==0) {
			echo "<br><br>";
			echo gettext("Il n'existe aucun parc. Vous devez d'abord créer un parc");
			include ("pdp.inc.php");
			exit;
		}
		sort($list_parcs);

		echo "<form method=\"post\" action=\"".$_SERVER['PHP_SELF']."\">\n";

		// Affichage des parcs sur 3/4 colonnes
		$nb_parcs_par_colonne=round(count($list_parcs)/3);
		echo "<table border='0'>\n";
		echo "<tr valign='top'>\n";
		echo "<td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>\n";
		echo "<td align='left'>\n";
		for ($loop=0; $loop < count($list_parcs); $loop++) {
			if(($loop>0)&&(round($loop/$nb_parcs_par_colonne)==$loop/$nb_parcs_par_colonne)){
				echo "</td>\n";
				echo "<td align='left'>\n";
			}

			if(($restriction_parcs=="n")||(in_array($list_parcs[$loop]["cn"], $tab_delegated_parcs))) {
				echo "<label for='parc_$loop'><input type='checkbox' id='parc_$loop' name='parc[]' value=\"".$list_parcs[$loop]["cn"]."\"";
				if(count($list_parcs)==1) {echo " checked";}
				echo " />".$list_parcs[$loop]["cn"]."</label>\n";
				echo "<br />\n";
			}
		}

		echo "</td>\n";
		echo "</tr>\n";
		echo "</table>\n";

		echo "<p align='center'><input type=\"submit\" name=\"submit\" value=\"Valider\" /></p>\n";

		echo "</form>\n";


		echo "<script type='text/javascript'>
nb_parcs=0;
id_parc='';
for(i=0;i<$loop;i++) {
	if(document.getElementById('parc_'+i)) {
		nb_parcs++;
		id_parc='parc_'+i;
	}
}
if(nb_parcs==1) {
	document.getElementById(id_parc).checked=true;
}
</script>\n";

		echo "<p><a href='index.php'>Retour à l'index</a>.</p>\n";
	}
	else {
		if(!isset($_POST['parametrage_action'])){

			echo "<form method=\"post\" action=\"".$_SERVER['PHP_SELF']."\">\n";
			echo "<input type=\"hidden\" name=\"parametrage_action\" value=\"1\" />\n";
			$max_eff_parc=0;
			for($i=0;$i<count($parc);$i++){

				echo "<h2>Parc $parc[$i]</h2>\n";
				echo "<input type=\"hidden\" name=\"parc[]\" value=\"$parc[$i]\" />\n";

				$mp=gof_members($parc[$i],"parcs",1);
				$nombre_machine=count($mp);
				sort($mp);

				//echo "<table border='1'>\n";
				echo "<table class='crob'>\n";
				echo "<tr>\n";

				echo "<th>Nom</th>\n";
				echo "<th>Etat</th>\n";
				echo "<th>Session</th>\n";
				echo "<th>Config DHCP</th>\n";  
                                echo "<th>Dernier Rapport</th>\n";
                                

				//echo "<th>Sauvegarde</th>\n";
				echo "<th>Rapport<br />\n";
				echo "<a href='#' onclick='check_machine($i,\"check\");return false'><img src=\"../elements/images/enabled.gif\" border='0' alt=\"Tout cocher\" title=\"Tout cocher\" /></a>\n";
				echo " / <a href='#' onclick='check_machine($i,\"uncheck\");return false'><img src=\"../elements/images/disabled.gif\" border='0' alt=\"Tout décocher\" title=\"Tout décocher\" /></a>\n";
				echo "</th>\n";
				echo "<th>Actions programmées</th>\n";
				echo "</tr>\n";

				for ($loop=0; $loop < count($mp); $loop++) {
					$mpenc=urlencode($mp[$loop]);

					// Test si on a une imprimante ou une machine
					$resultat=search_imprimantes("printer-name=$mpenc","printers");
					$suisje_printer="non";
					for ($loopp=0; $loopp < count($resultat); $loopp++) {
						if ($mpenc==$resultat[$loopp]['printer-name']) {
							$suisje_printer="yes";
							continue;
						}
					}

					if($suisje_printer=="non") {
						// Réinitialisation:
						$id_machine="";

						echo "<tr>\n";
						echo "<td width='20%'>".$mp[$loop]."</td>\n";

						// Etat: allumé ou éteint
						echo "<td width='20%'>";
						$mp_curr=search_machines2("(&(cn=$mpenc)(objectClass=ipHost))","computers");
						if ($mp_curr[0]["ipHostNumber"]) {
							$iphost=$mp_curr[0]["ipHostNumber"];

							echo "<div id='divip$loop'>Patientez</div>\n";
							echo "<script type='text/javascript'>
								// <![CDATA[
								new Ajax.Updater($('divip$loop'),'ajax_lib.php?ip=$iphost&mode=ping_ip',{method: 'get'});
								//]]>
							</script>\n";
						}
						echo "</td>\n";


						// Session: ouverte ou pas... sous quelle identité
						echo "<td width='20%'>\n";
						echo "<div id='divsession$loop'>Patientez</div>\n";
						echo "<script type='text/javascript'>
							// <![CDATA[
							new Ajax.Updater($('divsession$loop'),'ajax_lib.php?nom_machine=".$mp[$loop]."&mode=session',{method: 'get'});
							//]]>
						</script>\n";
						echo "</td>\n";


						// Etat config DHCP:
						// Par la suite il ne faudra pas prendre les IP dans l'annuaire,
						// mais dans la config DHCP parce que ce sont ces IP qui seront attribuées lors du boot PXE
						echo "<td width='20%'>\n";
						//$mp_curr=search_machines("(&(cn=$mpenc)(objectClass=ipHost))","computers");
						if ($mp_curr[0]["macAddress"]) {
							$sql="SELECT * FROM se3_dhcp WHERE mac='".$mp_curr[0]["macAddress"]."';";
							// mp_curr[0]["macAddress"] correspond à une adresse mac recherchée dans l'annuaire LDAP.
							// Si les machines ont été changées et que l'on a ré-attribué le nom, il faut penser à nettoyer l'entrée dans l'annuaire:
							// source /usr/share/se3/sbin/variables_admin_ldap.sh
							// ldapdelete -x -D $ROOTDN -w $PASSDN cn=NOM_MACHINE,ou=Computers,$BASEDN
							// Et se reconnecter une fois sur la machine pour que le connexion.pl renseigne une nouvelle entrée cn=NOM_MACHINE
							//echo "$sql<br />";
							$res=mysql_query($sql);
							if(mysql_num_rows($res)>0) {
								$lig=mysql_fetch_object($res);
								$id_machine=$lig->id;

								//echo $lig->ip;
								echo "<img src=\"../elements/images/enabled.gif\" border='0' alt=\"$lig->ip\" title=\"$lig->ip\" />";
							}
							else {
								echo "<img src=\"../elements/images/disabled.gif\" border='0' alt=\"Pas d'adresse IP attribuée\" title=\"Pas d'adresse IP attribuée\" />";
							}
						}
						else {
							echo "<img src=\"../elements/images/disabled.gif\" border='0' alt=\"Pas d'adresse MAC dans l'annuaire???\" title=\"Pas d'adresse MAC dans l'annuaire???\" />";
						}
						echo "</td>\n";


						// Sélection des machines à sauvegarder:
						echo "<td width='20%'>\n";
						/*
						foreach($mp_curr[0] as $champ => $valeur) {
							echo "\$mp_curr[0]['$champ']=$valeur<br />";
						}
						*/
						if($id_machine!=""){
							echo "<input type='checkbox' name='id_machine[]' id='machine_".$i."_".$loop."' value='$id_machine' />\n";
						}
						else {
							echo "<img src=\"../elements/images/disabled.gif\" border='0' alt=\"Il faut commencer par effectuer la configuration DHCP\" title=\"Il faut commencer par effectuer la configuration DHCP\" />";
						}
						echo "</td>\n";

                                                //dernier rapport
                                                
                                                $sql="SELECT * FROM se3_tftp_rapports WHERE name='".$mp[$loop]."' ORDER BY date DESC LIMIT 1;";
                                                $res_rapport_tftp=mysql_query($sql);
                                                if(mysql_num_rows($res_rapport_tftp)>0) {
                                                    $lig=mysql_fetch_object($res_rapport_tftp);
                                                    echo "<td align=\"center\">";
                                                    echo "<span style='font-size: x-small;' title='Dernier rapport: $lig->tache ($lig->statut)'><a href=\"../tftp/visu_rapport.php?id_machine=$lig->id\" target='_blank'>".$lig->date."</a></span>\n";
                                                    $st="$lig->statut";
                                                    if($st=="SUCCES") {
							$cl="green";
							} else {
							$cl="red";
                                                        }
                                                    echo "<FONT color=$cl size=1>"."$lig->statut"."</font>";
                                                    echo "</td>\n";
                                                }
                                                else {
                                                    echo "<td align=\"center\" style='color:purple'>".gettext("Aucun rapport")."</td>\n";
                                                    }
                                                
                                                
                                                
                                                
                                                
						// Action programmée
						echo "<td>\n";
						if($id_machine!=""){
							$sql="SELECT * FROM se3_tftp_action WHERE id='".$id_machine."';";
							$res=mysql_query($sql);
							if(mysql_num_rows($res)>0) {
								$lig=mysql_fetch_object($res);
								echo "<a href='visu_action.php?id_machine=$id_machine' target='_blank'>$lig->type programmé(e)</a>";
							}
							else {
								echo "<img src=\"../elements/images/disabled.gif\" border='0' alt=\"Pas d'action programmée\" title=\"Pas d'action programmée\" />";
							}
						}
						echo "</td>\n";

						echo "</tr>\n";
					}
				}
				echo "</table>\n";
				if($max_eff_parc<$loop) {$max_eff_parc=$loop;}
			}

			echo "<script type='text/javascript'>
	function check_machine(num_parc,mode) {
		for(i=0;i<$max_eff_parc;i++){
			if(document.getElementById('machine_'+num_parc+'_'+i)){
				if(mode=='check'){
					document.getElementById('machine_'+num_parc+'_'+i).checked=true;
				}
				else{
					document.getElementById('machine_'+num_parc+'_'+i).checked=false;
				}
			}
		}
	}
</script>\n";

			echo "<p align='center'><input type=\"submit\" name=\"submit\" value=\"Valider\" /></p>\n";
			echo "</form>\n";


		}
		else {
			$validation_parametres=isset($_POST['validation_parametres']) ? $_POST['validation_parametres'] : (isset($_GET['validation_parametres']) ? $_GET['validation_parametres'] : NULL);
			if(!isset($validation_parametres)) {
				echo "<h2>Paramétrage de la remontée de rapport</h2>\n";

				$nombre_machines=count($id_machine);
				if($nombre_machines==0){
					echo "<p>ERREUR: Il faut choisir au moins une machine.</p>\n";

					echo "<p><a href='#' onclick='history.go(-1);'>Retour au choix des machines sur lesquelles programmer une remontée de rapport</a>.</p>\n";

					echo "<p><a href='".$_SERVER['PHP_SELF']."'>Retour au choix du/des parc(s)</a>.</p>\n";
					include ("pdp.inc.php");
					exit();
				}

				echo "<form method=\"post\" action=\"".$_SERVER['PHP_SELF']."\">\n";
				echo "<input type=\"hidden\" name=\"parametrage_action\" value=\"1\" />\n";
				// Liste des parcs:
				for($i=0;$i<count($parc);$i++){
					echo "<input type=\"hidden\" name=\"parc[]\" value=\"$parc[$i]\" />\n";
				}

				// Liste des machines sur lesquelles lancer la sauvegarde:
				$chaine="";
				for($i=0;$i<count($id_machine);$i++){
					if($i>0) {$chaine.=", ";}
					$sql="SELECT * FROM se3_dhcp WHERE id='".$id_machine[$i]."';";
					//echo "$sql<br />";
					$res=mysql_query($sql);
					if(mysql_num_rows($res)>0) {
						$lig=mysql_fetch_object($res);
						$chaine.=$lig->name;
						echo "<input type=\"hidden\" name=\"id_machine[]\" value=\"$id_machine[$i]\" />\n";
					}
				}
				if(count($id_machine)>1){$s="s";}else{$s="";}
				echo "<p>Machine$s concernée$s: $chaine</p>\n";


				// Date pour le nom de l'image à générer:
				$aujourdhui = getdate();
				$mois_se3 = sprintf("%02d",$aujourdhui['mon']);
				$jour_se3 = sprintf("%02d",$aujourdhui['mday']);
				$annee_se3 = $aujourdhui['year'];
				$heure_se3 = sprintf("%02d",$aujourdhui['hours']);
				$minute_se3 = sprintf("%02d",$aujourdhui['minutes']);
				$seconde_se3 = sprintf("%02d",$aujourdhui['seconds']);

				$date_se3=$annee_se3.$mois_se3.$jour_se3;

				echo "<p>Choisissez les paramètres pour la remontée de rapport: <br />\n";

				$temoin_sysresccd=check_sysresccd_files();

				if($temoin_sysresccd=="y") {
					// Il faut aussi le noyau et l'initram.igz dans /tftpboot, 
					echo "<input type='radio' name='distrib' id='distrib_slitaz' value='slitaz' onchange='affiche_sections_distrib()' /><label for='distrib_slitaz'>Utiliser la distribution SliTaz</label><br />\n";
					echo "<input type='radio' name='distrib' id='distrib_sysresccd' value='sysresccd' onchange='affiche_sections_distrib()' checked /><label for='distrib_sysresccd'>Utiliser la distribution SysRescCD</label> (<i>plus long à booter et 300Mo de RAM minimum, mais meilleure détection des pilotes</i>)<br />\n";

echo "<div id='div_sysresccd_kernel'>\n";
echo "<table border='0'>\n";
echo "<tr>\n";
echo "<td valign='top'>\n";
echo "Utiliser le noyau&nbsp;: ";
echo "</td>\n";
echo "<td>\n";
echo "<input type='radio' name='sysresccd_kernel' id='sysresccd_kernel_auto' value='auto' checked /><label for='sysresccd_kernel_auto'>auto</label><br />\n";
echo "<input type='radio' name='sysresccd_kernel' id='sysresccd_kernel_rescuecd' value='rescue32' /><label for='sysresccd_kernel_rescuecd'>rescue32</label><br />\n";
echo "<input type='radio' name='sysresccd_kernel' id='sysresccd_kernel_altker32' value='altker32' /><label for='sysresccd_kernel_altker32'>altker32</label><br />\n";
echo "<input type='radio' name='sysresccd_kernel' id='sysresccd_kernel_rescue64' value='rescue64' /><label for='sysresccd_kernel_rescue64'>rescue64</label><br />\n";
echo "<input type='radio' name='sysresccd_kernel' id='sysresccd_kernel_altker64' value='altker64' /><label for='sysresccd_kernel_altker64'>altker64</label><br />\n";
echo "</td>\n";
echo "</tr>\n";
echo "</table>\n";
echo "</div>\n";

				}
				else {
					echo "<input type=\"hidden\" name=\"distrib\" value=\"slitaz\" />\n";
				}

				echo "<table border='0'>\n";
				/*
				echo "<tr><td>Nom de la sauvegarde: </td><td><input type='text' name='nom_image' value='image_$date_se3' />\n";
				echo "<u onmouseover=\"this.T_SHADOWWIDTH=5;this.T_STICKY=1;return escape".gettext("('Si vous laissez vide, un nom du type image_NOM_PARTITION_DATE_HEURE_MINUTE_SECONDE sera utilisé.')")."\"><img name=\"action_image1\"  src=\"../elements/images/help-info.gif\"></u>\n";
				echo "</td></tr>\n";

				echo "<tr><td>Partition à sauvegarder: </td><td><input type='text' name='src_part' value='auto' />\n";
				echo "<u onmouseover=\"this.T_SHADOWWIDTH=5;this.T_STICKY=1;return escape".gettext("('Proposer hda1, sda1,... selon les cas, ou laissez \'auto\' si la première partition du disque est bien la partition système à sauvegarder.')")."\"><img name=\"action_image2\"  src=\"../elements/images/help-info.gif\"></u>\n";
				echo "</td></tr>\n";

				echo "<tr><td>Partition de stockage: </td><td><input type='text' name='dest_part' value='auto' />\n";
				echo "<u onmouseover=\"this.T_SHADOWWIDTH=5;this.T_STICKY=1;return escape".gettext("('Proposer hda5, sda5,... selon les cas, ou laissez \'auto\' si la première partition Linux (<i>ou à défaut W$ après la partition système</i>) est bien la partition de stockage.')")."\"><img name=\"action_image3\"  src=\"../elements/images/help-info.gif\"></u>\n";
				echo "</td></tr>\n";
				*/

				if(($temoin_sysresccd=="y")&&(crob_getParam('srcd_scripts_vers')>='20110910')) {
					echo "<tr id='tr_authorized_keys'>\n";
					echo "<td>Url authorized_keys&nbsp;: </td>\n";
					echo "<td><input type='checkbox' name='prendre_en_compte_url_authorized_keys' value='y' /> \n";
					echo "<input type='text' name='url_authorized_keys' value='".crob_getParam('url_authorized_keys')."' size='40' />\n";
					echo "<u onmouseover=\"this.T_SHADOWWIDTH=5;this.T_STICKY=1;return escape".gettext("('Un fichier authorized_keys peut &ecirc;tre mis en place pour permettre un acc&egrave;s SSH au poste inspect&eacute;.')")."\">\n";
					echo "<img name=\"action_image3\"  src=\"../elements/images/help-info.gif\"></u>\n";
					echo "</td>\n";
					echo "</tr>\n";
				}

				echo "<tr><td valign='top'>Rebooter en fin de rapport: </td>\n";
				echo "<td>\n";
				echo "<input type='radio' name='auto_reboot' value='y' checked />\n";
				echo "</td>\n";
				echo "</tr>\n";

				echo "<tr><td valign='top'>Eteindre en fin de rapport: </td>\n";
				echo "<td>\n";
				echo "<input type='radio' name='auto_reboot' value='halt' />\n";
				echo "</td>\n";
				echo "</tr>\n";

				echo "<tr><td valign='top'>Ne pas rebooter ni éteindre la machine<br />en fin de rapport: </td>\n";
				echo "<td>\n";
				echo "<input type='radio' name='auto_reboot' value='n' />\n";
				echo "</td>\n";
				echo "</tr>\n";

				echo "<tr><td valign='top'>\n";
				echo "Délai avant reboot/arrêt:</td>\n";
				echo "<td>\n";
				echo "<input type='text' name='delais_reboot' value='90' size='3' />\n";
				echo "<u onmouseover=\"this.T_SHADOWWIDTH=5;this.T_STICKY=1;return escape".gettext("('Le délai doit être supérieur à 60 secondes pour permettre la récupération du rapport.')")."\"><img name=\"action_image4\"  src=\"../elements/images/help-info.gif\"></u>\n";
				echo "</td>\n";
				echo "</tr>\n";

				echo "<tr><td valign='top'>Pour la ou les machines sélectionnées: </td>\n";
				echo "<td>\n";
					echo "<table border='0'>\n";
					echo "<tr><td valign='top'><input type='checkbox' id='wake' name='wake' value='y' checked /> </td><td><label for='wake'>Démarrer les machines par Wake-On-Lan/etherwake<br />si elles sont éteintes.</label></td></tr>\n";
					echo "<tr><td valign='top'><input type='radio' id='shutdown_reboot_wait1' name='shutdown_reboot' value='wait1' /> </td><td><label for='shutdown_reboot_wait1'>Attendre le reboot des machines<br />même si aucune session n'est ouverte,</label></td></tr>\n";
					echo "<tr><td valign='top'><input type='radio' id='shutdown_reboot_wait2' name='shutdown_reboot' value='wait2' checked /> </td><td><label for='shutdown_reboot_wait2'>Redémarrer les machines sans session ouverte<br />et attendre le reboot pour les machines<br />qui ont des sessions ouvertes,</label></td></tr>\n";
					echo "<tr><td valign='top'><input type='radio' id='shutdown_reboot_reboot' name='shutdown_reboot' value='reboot' /> </td><td><label for='shutdown_reboot_reboot'>Redémarrer les machines<br />même si une session est ouverte (<i>pô cool</i>).</label></td></tr>\n";
					echo "</table>\n";
				echo "</td></tr>\n";

				echo "</table>\n";

				echo "<p align='center'><input type=\"submit\" name=\"validation_parametres\" value=\"Valider\" /></p>\n";
				echo "</form>\n";

echo "<script type='text/javascript'>
function affiche_sections_distrib() {
	if(document.getElementById('distrib_sysresccd').checked==true) {
		distrib='sysresccd';
	}
	else {
		distrib='slitaz';
	}
	
	if(distrib=='slitaz') {
		document.getElementById('div_sysresccd_kernel').style.display='none';
		document.getElementById('tr_authorized_keys').style.display='none';
	}
	else {
		document.getElementById('div_sysresccd_kernel').style.display='block';
		document.getElementById('tr_authorized_keys').style.display='';
	}
}

affiche_sections_distrib();
</script>\n";

				echo "<p><i>NOTES:</i></p>\n";
				echo "<ul>\n";
				//echo "<li>Ce choix nécessite une partition de sauvegarde sur la machine.</li>\n";
				echo "<li><b>Attention:</b > Le délai avant reboot ajouté au temps de l'opération lancée doit dépasser la périodicité du script controle_actions_tftp.sh en crontab.<br />
				Ce délai doit aussi permettre de récupérer en http://IP_CLIENT/~hacker/Public/*.txt des informations sur le succès ou l'échec de l'opération.<br />
				Une tâche cron se charge d'effectuer le 'wget' sur les infos, puis le remplissage d'une table MySQL.<br />
				La tâche cron est lancée toutes les 60s.</li>\n";
				echo "<li>Pour que l'opération puisse être entièrement provoquée depuis le serveur, il faut que les postes clients soient configurés pour booter en PXE (<i>ou au moins s'éveiller (wol) en bootant sur le réseau</i>).<br />Dans le cas contraire, vous devrez passer sur les postes et presser F12 pour choisir de booter en PXE.</li>\n";
				echo "</ul>\n";

			}
			else {
				echo "<h2>Validation des paramètres de la récupération de rapports</h2>\n";

				$opt_url_authorized_keys="";
				if((isset($_POST['prendre_en_compte_url_authorized_keys']))&&(isset($_POST['url_authorized_keys']))&&($_POST['url_authorized_keys']!='')&&(preg_replace('|[A-Za-z0-9/:_\.\-]|','',$_POST['url_authorized_keys'])=='')) {
					$opt_url_authorized_keys="url_authorized_keys=".$_POST['url_authorized_keys'];
					crob_setParam('url_authorized_keys',$_POST['url_authorized_keys'],'Url fichier authorized_keys pour acces ssh aux clients TFTP');
				}

				echo "<p>Rappel des paramètres:</p>\n";

				$temoin_sysresccd=check_sysresccd_files();

				if($temoin_sysresccd=="y") {
					echo "<table class='crob'>\n";
					echo "<tr>\n";
					echo "<th style='text-align:left;'>Distribution linux à utiliser: </th>\n";
					echo "<td>\n";
					echo $distrib;
					if($distrib=='sysresccd') {
						echo " (<i>noyau $sysresccd_kernel</i>)";
					}
					echo "<input type=\"hidden\" name=\"distrib\" value=\"$distrib\" />\n";
					echo "</td>\n";
					echo "</tr>\n";
				}
				else {
					echo "<input type=\"hidden\" name=\"distrib\" value=\"slitaz\" />\n";
					echo "<table class='crob'>\n";
				}
				/*
				echo "<tr>\n";
				echo "<th style='text-align:left;'>Nom de l'image: </th>\n";
				echo "<td>\n";
				if($nom_image=="") {echo "Nom généré automatiquement lors de la sauvegarde.";} else {echo $nom_image;}
				echo "</td>\n";
				echo "</tr>\n";

				echo "<tr>\n";
				echo "<th style='text-align:left;'>Partition à sauvegarder: </th>\n";
				echo "<td>\n";
				if($src_part=="auto") {echo "Détectée automatiquement lors de la sauvegarde.";} else {echo $src_part;}
				echo "</td>\n";
				echo "</tr>\n";

				echo "<tr>\n";
				echo "<th style='text-align:left;'>Partition de stockage de la sauvegarde: </th>\n";
				echo "<td>\n";
				if($dest_part=="auto") {echo "Détectée automatiquement lors de la sauvegarde.";} else {echo $dest_part;}
				echo "</td>\n";
				echo "</tr>\n";
				*/

				echo "<tr>\n";
				echo "<th style='text-align:left;'>Rebooter en fin d'opération: </th>\n";
				echo "<td>\n";
				echo $auto_reboot;
				echo "</td>\n";
				echo "</tr>\n";

				//if($auto_reboot=='y') {
				if(($auto_reboot=='y')||($auto_reboot=='halt')) {
					echo "<tr>\n";
					echo "<th style='text-align:left;'>Délai avant reboot: </th>\n";
					echo "<td>\n";
					echo "$delais_reboot s";
					echo "</td>\n";
					echo "</tr>\n";
				}

				echo "</table>\n";


				echo "<p>Génération du fichier dans /tftpboot/pxelinux.cfg/ pour la remontée de rapports.<br />\n";

				// BOUCLE SUR LA LISTE DES $id_machine[$i]

				// Numéro de l'opération de remontée de rapport:
				$num_op=get_free_se3_action_tftp_num_op();
				for($i=0;$i<count($id_machine);$i++) {
					$sql="SELECT * FROM se3_dhcp WHERE id='".$id_machine[$i]."';";
					//echo "$sql<br />";
					$res=mysql_query($sql);
					if(mysql_num_rows($res)==0) {
						echo "<span style='color:red;'>La machine d'identifiant $id_machine[$i] n'existe pas dans 'se3_dhcp'.</span><br />\n";
					}
					else {
						$temoin_erreur="n";

						$lig=mysql_fetch_object($res);
						$mac_machine=$lig->mac;
						$nom_machine=$lig->name;
						$ip_machine=$lig->ip;

						if($restriction_parcs=="y") {
							$temoin_erreur='y';
							for($loop=0; $loop<count($tab_delegated_parcs);$loop++) {
								// La machine est-elle dans un des parcs délégués?
								if(is_machine_in_parc($nom_machine,$tab_delegated_parcs[$loop])) {$temoin_erreur='n';break;}
							}
						}

						if($temoin_erreur=="y") {
							echo "<p style='color:red'>La machine $nom_machine ne vous est pas déléguée</p>\n";
						}
						else {
							echo "Génération pour $nom_machine: ";
	
							$corrige_mac=strtolower(strtr($mac_machine,":","-"));
	
							$chemin="/usr/share/se3/scripts";
	
							if($distrib=='slitaz') {
								$ajout_kernel="";
							}
							else {
								$ajout_kernel="|kernel=$sysresccd_kernel";
							}
	
							if($distrib=='slitaz') {
								//$resultat=exec("/usr/bin/sudo $chemin/pxe_gen_cfg.sh 'rapport' '$corrige_mac' '$ip_machine' '$nom_machine' '$auto_reboot' '$delais_reboot'", $retour);
								$resultat=exec("/usr/bin/sudo $chemin/pxe_gen_cfg.sh 'rapport' 'mac=$corrige_mac ip=$ip_machine pc=$nom_machine auto_reboot=$auto_reboot delais_reboot=$delais_reboot'", $retour);
							}
							else {
								//$resultat=exec("/usr/bin/sudo $chemin/pxe_gen_cfg.sh 'sysresccd_rapport' '$corrige_mac' '$ip_machine' '$nom_machine' '$auto_reboot' '$delais_reboot'", $retour);
								$resultat=exec("/usr/bin/sudo $chemin/pxe_gen_cfg.sh 'sysresccd_rapport' 'mac=$corrige_mac ip=$ip_machine pc=$nom_machine auto_reboot=$auto_reboot delais_reboot=$delais_reboot kernel=$sysresccd_kernel $opt_url_authorized_keys'", $retour);
							}
	
							if(count($retour)>0){
								//echo "<p>";
								//echo "<span style='color:red;'>Il semble que la génération du fichier ait échoué...</span><br />\n";
								echo "<span style='color:red;'>ECHEC de la génération du fichier</span><br />\n";
								for($j=0;$j<count($retour);$j++){
									echo "$retour[$j]<br />\n";
								}
								$temoin_erreur="y";
								//echo "</p>\n";
							}
							else {
								$sql="DELETE FROM se3_tftp_action WHERE id='$id_machine[$i]';";
								$suppr=mysql_query($sql);
	
								$timestamp=time();
								$sql="INSERT INTO se3_tftp_action SET id='$id_machine[$i]',
																		mac='$mac_machine',
																		name='$nom_machine',
																		date='$timestamp',
																		type='rapport',
																		num_op='$num_op',
																		infos='auto_reboot=$auto_reboot|delais_reboot=${delais_reboot}${ajout_kernel}';";
								$insert=mysql_query($sql);
								if(!$insert) {
									echo "<span style='color:red;'>ECHEC de l'enregistrement dans 'se3_tftp_action'</span><br />\n";
									$temoin_erreur="y";
								}
	
								// Génération du lanceur de récupération:
								//$dossier="/var/se3/tmp/tftp/$id_machine[$i]";
								$dossier="/etc/se3/www-tools/tftp/$id_machine[$i]";
								if(!file_exists($dossier)) { mkdir($dossier,0700);}
								$lanceur_recup="$dossier/lanceur_recup_rapport_action_tftp.sh";
								$fich=fopen($lanceur_recup,"w+");
								// On donne 4H pour que la récup soit effectuée:
								$timestamp_limit=time()+4*3600;
								//fwrite($fich,"/usr/share/se3/scripts/recup_rapport.php '$id_machine[$i]' '$ip_machine' 'rapport' '$timestamp_limit'");
								if($distrib=='slitaz') {
									$mode_rapport="rapport";
								}
								else {
									$mode_rapport="rapport_sysresccd";
								}
								fwrite($fich,"sudo /usr/share/se3/scripts/recup_rapport.php '$id_machine[$i]' '$ip_machine' '$mode_rapport' '$timestamp_limit'");
								fclose($fich);
								chmod($lanceur_recup,0750);
	
								// Ménage dans les tâches précédentes
								@exec("sudo /usr/share/se3/scripts/se3_tftp_menage_atq.sh $id_machine[$i]",$retour);
	
								// Planification de la tâche
								//@exec("at -f $lanceur_recup now + 1 minute 2>/dev/null",$retour);
								@exec("at -f $lanceur_recup now + 1 minute 2>$dossier/at.txt",$retour);
								//passthru("at -f $lanceur_recup now + 1 minute",$retour);
								if($retour) {
									echo "<span style='color:red;'>ECHEC de la planification de la tâche.</span><br />\n";
									for($j=0;$j<count($retour);$j++){echo "$retour[$j]<br />\n";}
									//echo "$retour<br />\n";
									$temoin_erreur="y";
								}
	
								/*
								// Avec ça on arrive à récupérer l'info:
								//	-warning: commands will be executed using /bin/sh -
								//	-job 1572 at 2008-03-01 15:13 -
								// Mais une fois le at repoussé, ce n'est plus www-se3, mais root qui en est proprio...
								if(file_exists("$dossier/at.txt")) {
									$fp=fopen("$dossier/at.txt","r");
									while(!feof($fp)) {
										$ligne=fgets($fp,4096);
										echo "<p>-".$ligne."-</p>";
									}
									fclose($fp);
								}
								*/
	
								/*
								$fp=popen("at -f $lanceur_recup now + 1 minute","r");
								while(!feof($fp)) {
									$ligne=fgets($fp,4096);
									echo "<p>-".$ligne."-</p>";
								}
								fclose($fp);
								*/
	
								if($temoin_erreur=="n") {
									//echo "<span style='color:green;'>OK</span><br />\n";
									echo "<span style='color:green;'>OK</span>\n";
									// Application de l'action choisie:
									echo " <span id='wake_shutdown_or_reboot_$i'></span>";
	
									echo "<script type='text/javascript'>
										// <![CDATA[
										new Ajax.Updater($('wake_shutdown_or_reboot_$i'),'ajax_lib.php?ip=$ip_machine&nom=$nom_machine&mode=wake_shutdown_or_reboot&wake=$wake&shutdown_reboot=$shutdown_reboot',{method: 'get'});
										//]]>
									</script>\n";
	
	
									echo "<br />\n";
								}
							}
						}
					}
				}

				// +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-
				// POUVOIR TAGUER DANS LA TABLE se3_dhcp LES MACHINES QUI PEUVENT BOOTER EN PXE
				// Ajouter un champ?
				// +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-


				// On n'affiche le fichier que pour le dernier (à titre d'info):
				if(isset($corrige_mac)) {
					//$fich=fopen("/tftpboot/pxelinux.cfg/01-$lig1->mac","r");
					$fich=fopen("/tftpboot/pxelinux.cfg/01-$corrige_mac","r");
					if($fich) {
						echo "<p>Pour information, voici le contenu du fichier généré:<br />\n";
						echo "<pre style='border:1px solid black; color:green;'>";
						while(!feof($fich)) {
							$ligne=fgets($fich,4096);
							echo htmlentities($ligne);
						}
						echo "</pre>\n";
						fclose($fich);
					}
					else {
						echo "<p style='color:red;'>Il n'a pas été possible d'ouvrir le fichier /tftpboot/pxelinux.cfg/01-$corrige_mac</p>\n";
					}
				}
			}
		}
		echo "<p><a href='".$_SERVER['PHP_SELF']."'>Retour au choix du/des parc(s)</a>.</p>\n";
	}
}
else {
	print (gettext("Vous n'avez pas les droits nécessaires pour ouvrir cette page..."));
}

// Footer
include ("pdp.inc.php");
?>
