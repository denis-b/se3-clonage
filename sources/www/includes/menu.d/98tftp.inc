<?
/* $Id$ */

	// Si le module se3-unattended est actif, on ajoute la ligne correspondante:
		$liens1 = array(array(
		"Serveur tftp", "parc_can_clone",3,
		"Configurer le module", "tftp/config_tftp.php","system_is_admin",3,
		"Consulter les actions", "tftp/consultation_tftp.php","parc_can_clone",3,
		"Programmer une sauvegarde", "tftp/action_svg_tftp.php","parc_can_clone",3,
		"Programmer une restauration", "tftp/action_rest_tftp.php","parc_can_clone",3,
		"Programmer un clonage", "tftp/action_clone_tftp.php","parc_can_clone",3,
		"Programmer un rapport", "tftp/action_rapport_tftp.php","parc_can_clone",3,
		"Programmer un memtest", "tftp/action_memtest_tftp.php","parc_can_clone",3,
                "D&eacute;ployer GNU/Linux", "tftp/action_unattend_linux_tftp.php","parc_can_clone",3 
		));
		
	if ($menu_unattended=="1") {
		array_push ($liens1[0], "Programmer une install XP", "tftp/action_unattend_xp_tftp.php","system_is_admin",3);
        }
	if ($menu_seven=="1") {
		array_push ($liens1[0], "Programmer une install seven", "tftp/action_seven_tftp.php","system_is_admin",3);
	}
	$liens=array_merge($liens,$liens1);


?>
