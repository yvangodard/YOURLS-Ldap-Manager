<?php
/*
Plugin Name: LDAP Manager
Description: Gestion des connexions avec un ou plusieurs Active Directory
Version: 1.1
Author: Jérôme LAFFORGUE
Author URI: http://www.imjweb.fr/
*/

@session_start();

// No direct call
if( !defined( 'YOURLS_ABSPATH' ) ) die();

/**
 * On enregistre la page dans le menu
 */
function ldap_manager_add_page() {
	yourls_register_plugin_page('ldap_manager', 'LDAP Manager', 'ldapmanager_do_page' );
}
yourls_add_action( 'plugins_loaded', 'ldap_manager_add_page' );

/**
 * Affichage de la page principale
 */
function ldapmanager_do_page() {

	echo '
		<h2>LDAP Administration Page</h2>
		<p>Gestion des connexions via Open LDAP</p>
		<a href="#" class="btn btn-primary" style="color:#FFF;" id="add-serveur-link"><i class="glyphicon glyphicon-plus"></i> Ajouter un serveur</a>
	';
	
	//Formulaire d'ajout
	echo'	
		<!-- Formulaire d\'ajout d\'un serveur -->
		<div class="well" style="margin-top:10px;display:none;">
			<form method="post" class="form" id="ServeurAddForm">
			<h3>Paramètres du serveur</h3>
			<input type="hidden" name="action" value="add_serveur" />
			<input type="hidden" name="nonce" value="$nonce" />
			<div class="form-group">
				<input type="text" name="data[Serveur][ldap_host]" value="" placeholder="Hôte" class="form-control input-sm" style="float:left;width:90%;" required="required" />
				<input type="text" name="data[Serveur][ldap_port]" value="" placeholder="Port" class="form-control input-sm" style="float:right;width:60px;margin-left:5px;" required="required"/>
				<div class="clearfix"></div>
			</div>
			<div class="form-group">
				<input type="text" name="data[Serveur][ldap_base_dn]" placeholder="DN Utilisateur (Autorisé à consulter l\'annuaire)" class="form-control input-sm" />
			</div>
			<div class="form-group">
				<input type="password" name="data[Serveur][ldap_password]" placeholder="Mot de passe" class="form-control input-sm" />
			</div>
			<div class="form-group">
				<input type="text" name="data[Serveur][ldap_dnracine]" placeholder="DN branche Utilisateurs" class="form-control input-sm" required="required" />
			</div>
			<div class="form-group">
				<input type="text" name="data[Serveur][ldap_filtres]" placeholder="Filtres" class="form-control input-sm" name="filtre"/>
			</div>
			<input type="submit" value="Ajouter" class="btn btn-primary" id="serveur-add-submit" />
			</form>
		</div>
		<!-- Fin formulaire -->
		<br /><br />';

		//Affichage du tableau listant les serveurs
		echo '<div class="panel panel-default">
		  <div class="panel-heading">
				Serveur(s) configuré(s) 
				<div style="float:right;display:none" class="alert alert-success" id="flash-notify-success"><i class="glyphicon glyphicon-ok"></i> La modification a bien été éffectuée</div>
				<div style="float:right;display:none" class="alert alert-danger" id="flash-notify-error"><i class="glyphicon glyphicon-remove"></i> Une erreur est survenue</div>
			</div>
		  <div class="panel-body">
			<div id="ajax-serveurs">	
				'.load_table_serveurs().'
			</div>
		
		<hr />
		<div id="ajax-edit-serveur">
			
		</div>
		
		  </div>
		</div>';

}

/**
* Fonction qui génère la table des serveurs AD avec Test de connexion
* $active_id (int) ID du serveur que l'on est en train de modifier
*/
function load_table_serveurs($active_id = null){

	//Si aucun paramètre n'est passé dans on fait la requête qui liste les serveurs
	if(!$tr_serveurs){
		$mysqli = new mysqli(YOURLS_DB_HOST, YOURLS_DB_USER, YOURLS_DB_PASS, YOURLS_DB_NAME);
		$serveurs = $mysqli->query('SELECT * FROM '.YOURLS_DB_PREFIX.'ldap');
		
		while($row = $serveurs->fetch_array())
		{
			
			$datas = unserialize(base64_decode($row['datas']));
			
			$class_active = ($row['id'] == $active_id)? 'active' : '';
			$connexion_checker = connexion_checker($datas);
			$connexion = ($connexion_checker['ldap_connect'] == true)? '<div class="connexion-success"></div>' : '<div class="connexion-error"></div>';
			
			$tr_serveurs .= '<tr id="serveur-'.$row['id'].'">';
			$tr_serveurs .= '<td> <i class="glyphicon glyphicon-trash delete-serveur-link" style="cursor:pointer;"></i></td>';
			$tr_serveurs .= '<td><a href="#" class="edit-serveur-link '.$class_active.'"><i class="glyphicon glyphicon-edit"></i> '.$datas['ldap_host'].' '.$datas['ldap_port'].'</a></td>';
			$tr_serveurs .= '<td>'.$connexion.'</td>';
			$tr_serveurs .= '<td><a href="#" class="show-users" data-toggle="modal" data-target="#serveur-'.$row['id'].'-users">'.$connexion_checker['count_users'].'</a>';
			$tr_serveurs .=	'<div class="modal fade" id="serveur-'.$row['id'].'-users" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
			  <div class="modal-dialog" >
			    <div class="modal-content">
			      <div class="modal-header">
			        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
			        <h4 class="modal-title" id="myModalLabel">Liste des utilisateurs</h4>
			      </div>
			      <div class="modal-body">
			    	 <ul>';
			
			foreach($connexion_checker['ldap_users'] as $user){
				$tr_serveurs .= '<li>'.$user['cn'].' ('.$user['uid'].')</li>';
			}
			
			$tr_serveurs .= '</ul></div>
			      <div class="modal-footer">
			        <button type="button" class="btn btn-default" data-dismiss="modal">Fermer</button>
			      </div>
			    </div>
			  </div>
			</div></div></td>';
			$tr_serveurs .= '</tr>';
			
		}
	}

	$table_serveurs = '<table id="table-serveurs" class="table table-striped">';
	$table_serveurs .= '<tr>
					<th><div class="ajax-loader" style="display:none;"></div></th>
					<th>Hôte</th>
					<th>Connectivité</th>
					<th>NB Utilisateurs</th>
				</tr>';
	$table_serveurs .= $tr_serveurs;
	$table_serveurs .= '</table>';
	
	return $table_serveurs;
}

/**
* Chargement en AJAX dans la table qui liste les serveurs
*/
function ajax_load_table_serveurs(){
	echo load_table_serveurs($_POST['active_id']);
}
yourls_add_action('yourls_ajax_load_table_serveurs', 'ajax_load_table_serveurs');

/**
 * Ajout d'un serveur
 */
function ajax_add_serveur(){
	
	$datas = base64_encode(serialize($_REQUEST['data']['Serveur']));
	$mysqli = new mysqli(YOURLS_DB_HOST, YOURLS_DB_USER, YOURLS_DB_PASS, YOURLS_DB_NAME);
	if($mysqli->query("INSERT INTO ".YOURLS_DB_PREFIX."ldap (id, name, datas, created) VALUES (NULL,'serveur', '".$datas."', now())")){
		$result = array('result' => true);
	} else {
		$result = array('result' => false);
		var_dump($mysqli->error);
	}
	$mysqli->close();
	
	echo json_encode($result);
}
yourls_add_action('yourls_ajax_add_serveur', 'ajax_add_serveur');

/**
 * Edition d'un serveur
 */
function ajax_edit_serveur($value){

	//Connexion BDD
	$mysqli = new mysqli(YOURLS_DB_HOST, YOURLS_DB_USER, YOURLS_DB_PASS, YOURLS_DB_NAME);

	$data = array();

	//MAJ d'un serveur
	if(isset($_POST['save_serveur']) && $_POST['save_serveur'] == "1" && isset($_POST['id'])){
		$datas = base64_encode(serialize($_POST['data']['Serveur']));

		if($mysqli->query('UPDATE '.YOURLS_DB_PREFIX.'ldap SET datas = "'.$datas.'" WHERE id = '.$_POST['id'].'')){
			$result = array('result' => true);
		} else {
			$result = array('result' => false);
		}

		header('Content-Type: application/json');
		echo json_encode($result);

	} //Affichage du fomrmulaire d'édition
	elseif(isset($_POST['id'])){

		$req = $mysqli->query('SELECT * FROM '.YOURLS_DB_PREFIX.'ldap WHERE id = '.$_POST['id'].'');
		$serveur = $req->fetch_assoc();
		$data = unserialize(base64_decode($serveur['datas']));


		echo '<form method="post" class="form" id="ServeurEditForm">
				<h3>Modifier la configuration d\'un serveur</h3>
				<input type="hidden" name="action" value="edit_serveur" />
				<input type="hidden" name="save_serveur" value="1" />
				<input type="hidden" name="id" value="'.$serveur['id'].'" />
				<div class="form-group">
					<input type="text" name="data[Serveur][ldap_host]" placeholder="Hôte" class="form-control input-sm" style="float:left;width:90%;" required="required" value="'.$data['ldap_host'].'" />
					<input type="text" name="data[Serveur][ldap_port]" placeholder="Port" class="form-control input-sm" style="float:right;width:60px;margin-left:5px;" required="required" value="'.$data['ldap_port'].'"/>
					<div class="clearfix"></div>
				</div>
				<div class="form-group">
					<input type="text" name="data[Serveur][ldap_base_dn]" placeholder="DN Utilisateur (Autorisé à consulter l\'annuaire)" class="form-control input-sm" value="'.$data['ldap_base_dn'].'" />
				</div>
				<div class="form-group">
					<input type="password" name="data[Serveur][ldap_password]" placeholder="Mot de passe" class="form-control input-sm" value="'.$data['ldap_password'].'"/>
				</div>
				<div class="form-group">
					<input type="text" name="data[Serveur][ldap_dnracine]" placeholder="DN branche Utilisateurs" class="form-control input-sm" required="required" value="'.$data['ldap_dnracine'].'"/>
				</div>
				<div class="form-group">
					<input type="text" name="data[Serveur][ldap_filtres]" placeholder="Filtres" class="form-control input-sm" value="'.$data['ldap_filtres'].'"/>
				</div>
				<input type="submit" value="Modifier" class="btn btn-primary" id="serveur-edit-submit" />
			</form>';
	}
}
yourls_add_action('yourls_ajax_edit_serveur', 'ajax_edit_serveur');

/**
 * Suprression d'un serveur
 */
function ajax_delete_serveur(){
	$id = $_POST['id'];
	$mysqli = new mysqli(YOURLS_DB_HOST, YOURLS_DB_USER, YOURLS_DB_PASS, YOURLS_DB_NAME);
	if($mysqli->query("DELETE FROM ".YOURLS_DB_PREFIX."ldap WHERE id = $id")){
		$result = array('result' => true);
	} else {
		$result = array('result' => false);
	}
	
	$mysqli->close();
	
	echo json_encode($result);
}
yourls_add_action('yourls_ajax_delete_serveur', 'ajax_delete_serveur');


/**
 * Vérifie si l'utilisateur qui tente de se connecter est bien identifié dans l'AD
 * @param unknown $value
 * @return unknown|boolean
 */
function ldap_is_valid_user($value){

	// doesn't work for API...

	if (yourls_is_API()){
		return ldap_pre_login_signature();
	}


	if(defined('YOURLS_USER')){
		return true;
	}
	
	if(isset($_SESSION['ldap_auth']) && $_SESSION['ldap_auth'] == true){
		yourls_set_user( $_SESSION['ldap_username'] );
		global $yourls_user_passwords;
		$yourls_user_passwords[$_SESSION['ldap_username']] = uniqid("",true);
		return true;
	}
	
	if(isset($_POST['username']) && isset($_POST['password']) && !empty($_POST['password'])){
		
		$mysqli = new mysqli(YOURLS_DB_HOST, YOURLS_DB_USER, YOURLS_DB_PASS, YOURLS_DB_NAME);
		$serveurs = $mysqli->query('SELECT * FROM '.YOURLS_DB_PREFIX.'ldap');

		while($row = $serveurs->fetch_array()){
			$datas = unserialize(base64_decode($row['datas']));
			$connexion = connexion_check_user($datas, $_POST);
			
			if($connexion == true){
				yourls_set_user($_POST['username']);
				global $yourls_user_passwords;
				$yourls_user_passwords[$_POST['username']] = uniqid("",true);
				$_SESSION['ldap_auth'] = true;
				$_SESSION['ldap_username'] = $_POST['username'];
		
				return true;
			}
		}
		
		return false;
	}
	
}
yourls_add_filter('is_valid_user', 'ldap_is_valid_user' );

function ldap_pre_login_signature(){
	$signature = ($_GET['signature'])? $_GET['signature'] : '';
	$signature = ($_POST['signature'])? $_POST['signature'] : '';
	if (yourls_is_API()) return search_signature_ad($signature);
	return false;
}
//yourls_add_action('pre_login', 'ldap_pre_login_signature');

/**
* Déconnexion du Yourls et suppression de la session LDAP
*/
function logout_ldap(){
	unset($_SESSION['ldap_auth']);
	unset($_SESSION['ldap_username']);
}
yourls_add_action('logout', 'logout_ldap');


/**
 *  Test connexion à un serveur AD
 */
function connexion_checker($ldap_params){
	
	//echo '<pre>'.var_dump($ldap_params).'</pre>';
	
	$ldap = ldap_connect($ldap_params['ldap_host'], $ldap_params['ldap_port']);
	ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
	ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
	
	$ldapbind = ldap_bind($ldap, $ldap_params['ldap_base_dn'], $ldap_params['ldap_password']);
		
	$number_returned = 0;
	$attributes_ad = array("displayName","uid","cn","givenName","sn","mail");
	
	if (!($search=@ldap_search($ldap, $ldap_params['ldap_dnracine'], $ldap_params['ldap_filtres'], $attributes_ad))){
		echo("Unable to search ldap server<br>");
		//echo("msg:'".ldap_error($ldap)."'</br>");#check the message again
	} else {
		$number_returned = ldap_count_entries($ldap,$search);
		$users = ldap_get_entries($ldap, $search);
		foreach($users as $user){
			//echo '<pre>'; var_dump($user); echo '</pre>';
			if(isset($user['uid'][0])){
				$ldap_users[] = array('uid' => $user['uid'][0], 'cn' => $user['cn'][0]);
			}
		}
	}
	
	return array('ldap_connect' => $ldapbind, 'count_users' => $number_returned, 'ldap_users' => $ldap_users);;
}

/**
 *  On cherche si la signature correspond à un identifiant présent dans un des AD enregistrés
 */
function search_signature_ad($signature){
	
	$mysqli = new mysqli(YOURLS_DB_HOST, YOURLS_DB_USER, YOURLS_DB_PASS, YOURLS_DB_NAME);
	$serveurs = $mysqli->query('SELECT * FROM '.YOURLS_DB_PREFIX.'ldap');
		
	while($row = $serveurs->fetch_array())
	{
		
		$datas = unserialize(base64_decode($row['datas']));

		$ldap = ldap_connect($datas['ldap_host'], $datas['ldap_port']);
		ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
		ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
		
		$ldapbind = ldap_bind($ldap, $datas['ldap_base_dn'], $datas['ldap_password']);
			
		$number_returned = 0;
		$attributes_ad = array("displayName","uid","cn","givenName","sn","mail");
		
		if (!($search=@ldap_search($ldap, $datas['ldap_dnracine'], $datas['ldap_filtres'], $attributes_ad))){
			echo("Unable to search ldap server<br>");
		} else {
			$number_returned = ldap_count_entries($ldap,$search);
			$users = ldap_get_entries($ldap, $search);
			foreach($users as $user){
				//echo '<pre>'; var_dump($user); echo '</pre>';
				if(isset($user['uid'][0]) && $signature ==  yourls_auth_signature($user['uid'][0])){
					return true;
				}
			}
		}
	}
	return false;
}

/**
 * Test d'authentification d'un utilisateur à un serveur LDAP
 */
	function connexion_check_user($ldap_params, $username_password){

		$ldap = @ldap_connect($ldap_params['ldap_host'], $ldap_params['ldap_port']);
		ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
		ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
	
		$username = 'uid='.$username_password['username'].','.$ldap_params['ldap_dnracine'];
		$password = $username_password['password'];
		
		//var_dump($password);
		$ldapbind = @ldap_bind($ldap, $username, $password);

		
		if (!($search=@ldap_search($ldap, $username, $ldap_params['ldap_filtres']))){
			echo("Unable to search ldap server<br>");
			//echo("msg:'".ldap_error($ldap)."'</br>");#check the message again
		} else {
			$number_returned = ldap_count_entries($ldap,$search);
			if($number_returned == 1){
				
				$username = $username_password['username'];
				
				yourls_set_user($username);
				global $yourls_user_passwords;
				$yourls_user_passwords[$username] = uniqid("",true);
				
				var_dump($yourls_user_passwords);
				
				return true;
			}
		}
		
	}

/**
 * Première activation du plugin - Création de la table LDAP
 */	
function ldap_manager_install(){
	$mysqli = new mysqli(YOURLS_DB_HOST, YOURLS_DB_USER, YOURLS_DB_PASS, YOURLS_DB_NAME);
	$mysqli->query(
	'CREATE TABLE IF NOT EXISTS `'.YOURLS_DB_PREFIX.'ldap` (
	  `id` int(11) NOT NULL AUTO_INCREMENT,
	  `name` varchar(255) NOT NULL,
	  `datas` text NOT NULL,
	  `created` datetime NOT NULL,
	   PRIMARY KEY (id)
	);');
}	
yourls_add_filter('activated_plugin', 'ldap_manager_install');
	


/**
 * Avant l'affichage de la page
 * Si on essaye d'afficher la page des plugins et que l'on est connecté via AD, on refuse
 * Si on est redirigé à l'accueil, on affiche un message d'erreur
 */
function pre_page(){
	$pagename = basename($_SERVER['PHP_SELF']);
	if($pagename == 'plugins.php' && isset($_SESSION['ldap_auth'])){
		yourls_redirect(YOURLS_SITE.'/admin/?action=manage_denied');
	}
	
	if(isset($_GET['action']) && $_GET['action'] == 'manage_denied'){
		yourls_add_notice('Vous n\'avez pas les droits nécéssaires pour gérer les plugins.', 'alert alert-danger');
	}
	
	
}
yourls_add_action('pre_page', pre_page());


/**
 * Chargement des scripts au lancement du plugin
 */
function load_css(){
	echo '<!-- Latest compiled and minified CSS -->
			<link rel="stylesheet" href="'.YOURLS_SITE.'/user/plugins/ldap-manager/css/bootstrap.css">
			<script src="http://jqueryvalidation.org/files/dist/jquery.validate.min.js"></script>
			<script src="//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/js/bootstrap.min.js"/></script>
			<script src="'.YOURLS_SITE.'/user/plugins/ldap-manager/js/ldap-plugin.js"></script>
			';
	
	//Si un utilisateur est connecté via LDAP on cache l'accès au menu de gestion des plugins en CSS
	if(isset($_SESSION['ldap_auth'])){
		echo '<style>#admin_menu_plugins_link{display:none;}</style>';
	}
}
yourls_add_action('html_head', 'load_css');