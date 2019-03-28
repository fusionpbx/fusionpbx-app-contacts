<?php
/*
	FusionPBX
	Version: MPL 1.1

	The contents of this file are subject to the Mozilla Public License Version
	1.1 (the "License"); you may not use this file except in compliance with
	the License. You may obtain a copy of the License at
	http://www.mozilla.org/MPL/

	Software distributed under the License is distributed on an "AS IS" basis,
	WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
	for the specific language governing rights and limitations under the
	License.

	The Original Code is FusionPBX

	The Initial Developer of the Original Code is
	Mark J Crane <markjcrane@fusionpbx.com>
	Portions created by the Initial Developer are Copyright (C) 2008-2018
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
	Luis Daniel Lucio Quiroz <dlucio@okay.com.mx>
*/

//includes
	require_once "root.php";
	require_once "resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!permission_exists('contact_attachment_edit') && !permission_exists('contact_attachment_add')) {
		echo "access denied"; exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//action add or update
	$contact_attachment_uuid = $_REQUEST['id'];
	$contact_uuid = $_REQUEST['contact_uuid'];

	if (is_uuid($contact_attachment_uuid) && is_uuid($contact_uuid)) {
		$action = 'update';
	}
	else if (is_uuid($contact_uuid)) {
		$action = 'add';
	}
	else {
		exit;
	}

//get http post variables and set them to php variables
	if (is_array($_POST) && sizeof($_POST) != 0) {

		$attachment = $_FILES['attachment'];
		$attachment_primary = check_str($_POST['attachment_primary']);
		$attachment_description = check_str($_POST['attachment_description']);

		if (!is_array($attachment) || sizeof($attachment) == 0) {
			$attachment_type = strtolower(pathinfo($_POST['attachment_filename'], PATHINFO_EXTENSION));
		}
		else {
			$attachment_type = strtolower(pathinfo($attachment['name'], PATHINFO_EXTENSION));
		}

		//unflag others as primary
			if ($attachment_primary && ($attachment_type == 'jpg' || $attachment_type == 'jpeg' || $attachment_type == 'gif' || $attachment_type == 'png')) {
				$sql = "update v_contact_attachments set attachment_primary = 0 ";
				$sql .= "where domain_uuid = '".$domain_uuid."' ";
				$sql .= "and contact_uuid = '".$contact_uuid."' ";
				$db->exec(check_sql($sql));
				unset($sql);
			}

		//format array
			$allowed_extensions = array_keys(json_decode($_SESSION['contact']['allowed_attachment_types']['text'], true));
			$array['contact_attachments'][$index]['contact_attachment_uuid'] = $action == 'update' ? $contact_attachment_uuid : uuid();
			$array['contact_attachments'][$index]['domain_uuid'] = $_SESSION['domain_uuid'];
			$array['contact_attachments'][$index]['contact_uuid'] = $contact_uuid;
			$array['contact_attachments'][$index]['attachment_primary'] = $attachment_primary == '1' && ($attachment_type == 'jpg' || $attachment_type == 'jpeg' || $attachment_type == 'gif' || $attachment_type == 'png') ? 1 : 0;
			if ($attachment['error'] == '0' && in_array(strtolower(pathinfo($attachment['name'], PATHINFO_EXTENSION)), $allowed_extensions)) {
				$array['contact_attachments'][$index]['attachment_filename'] = $attachment['name'];
				$array['contact_attachments'][$index]['attachment_content'] = base64_encode(file_get_contents($attachment['tmp_name']));
			}
			$array['contact_attachments'][$index]['attachment_description'] = $attachment_description;
			if ($action == 'add') {
				$array['contact_attachments'][$index]['attachment_uploaded_date'] = 'now()';
				$array['contact_attachments'][$index]['attachment_uploaded_user_uuid'] = $_SESSION['user_uuid'];
			}

		//save data
			$database = new database;
			$database->app_name = 'contacts';
			$database->app_uuid = '04481e0e-a478-c559-adad-52bd4174574c';
			$database->uuid($contact_attachment_uuid);
			$database->save($array);

		//redirect
			message::add($text['message-message_'.($action == 'update' ? 'updated' : 'added')]);
			header('Location: contact_edit.php?id='.$contact_uuid);
			exit;

	}

//get form data
	if (is_array($_GET) && sizeof($_GET) != 0) {
		$sql = "select * from v_contact_attachments ";
		$sql .= "where domain_uuid = '$domain_uuid' ";
		$sql .= "and contact_attachment_uuid = :contact_attachment_uuid ";
		$bind[':contact_attachment_uuid'] = $contact_attachment_uuid;
		$prep_statement = $db->prepare(check_sql($sql));
		$prep_statement->execute(is_array($bind) ? $bind : null);
		$row = $prep_statement->fetch(PDO::FETCH_NAMED);
		$attachment_primary = $row["attachment_primary"];
		$attachment_filename = $row["attachment_filename"];
		$attachment_content = $row["attachment_content"];
		$attachment_description = $row["attachment_description"];
		unset($sql, $bind, $prep_statement, $row);
	}

//show the header
	require_once "resources/header.php";
	if ($action == "update") {
		$document['title'] = $text['title-contact_attachment-edit'];
	}
	else if ($action == "add") {
		$document['title'] = $text['title-contact_attachment-add'];
	}

//show the content
	echo "<form method='post' name='frm' enctype='multipart/form-data' action=''>\n";
	echo "<input type='hidden' name='contact_uuid' value='".escape($contact_uuid)."'>\n";
	if ($action == "update") {
		echo "<input type='hidden' name='contact_attachment_uuid' value='".escape($contact_attachment_uuid)."'>\n";
	}
	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
	echo "<tr>\n";
	echo "<td align='left' valign='top' nowrap='nowrap'><b>";
	if ($action == "update") {
		echo $text['header-contact_attachment-edit'];
	}
	else if ($action == "add") {
		echo $text['header-contact_attachment-add'];
	}
	echo "</b></td>\n";
	echo "<td align='right' valign='top'>";
	echo "	<input type='button' class='btn' name='' alt='".$text['button-back']."' onclick=\"window.location='contact_edit.php?id=$contact_uuid'\" value='".$text['button-back']."'>";
	echo "	<input type='submit' name='submit' class='btn' value='".$text['button-save']."'>\n";
	echo "</td>\n";
	echo "</tr>\n";
	echo "</table>\n";
	echo "<br>\n";

	echo "<table width='100%'  border='0' cellpadding='0' cellspacing='0'>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-attachment']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	$attachment_type = strtolower(pathinfo($attachment_filename, PATHINFO_EXTENSION));
	if ($action == 'update') {
		echo "<input type='hidden' name='attachment_filename' value=\"".escape($attachment_filename)."\">\n";
		if ($attachment_type == 'jpg' || $attachment_type == 'jpeg' || $attachment_type == 'gif' || $attachment_type == 'png') {
			echo "<img src='data:image/".$attachment_type.";base64,".$attachment_content."' style='border: none; width: auto; max-height: 400px;' oncontextmenu=\"window.open('contact_attachment.php?id=".$contact_attachment_uuid."&action=download'); return false;\">";
		}
		else {
			echo "<a href='contact_attachment.php?id=".$contact_attachment_uuid."&action=download' style='font-size: 120%;'>".$attachment_filename."</a>";
		}
	}
	else {
		$allowed_attachment_types = json_decode($_SESSION['contact']['allowed_attachment_types']['text'], true);
		echo "	<input type='file' class='formfld' name='attachment' id='attachment' accept='.".implode(',.',array_keys($allowed_attachment_types))."'>\n";
		echo "	<span style='display: inline-block; margin-top: 5px; font-size: 80%;'>".strtoupper(implode(', ', array_keys($allowed_attachment_types)))."</span>";
	}
	echo "</td>\n";
	echo "</tr>\n";

	if ($action == 'update' && ($attachment_type == 'jpg' || $attachment_type == 'jpeg' || $attachment_type == 'gif' || $attachment_type == 'png')) {
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
		echo "	".$text['label-attachment_filename']."\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "	<a href='contact_attachment.php?id=".$contact_attachment_uuid."&action=download' style='font-size: 120%;'>".$attachment_filename."</a>";
		echo "</td>\n";
		echo "</tr>\n";
	}

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-primary']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<select class='formfld' name='attachment_primary' id='attachment_primary'>\n";
	echo "		<option value='0'>".$text['option-false']."</option>\n";
	echo "		<option value='1' ".(($attachment_primary) ? "selected" : null).">".$text['option-true']."</option>\n";
	echo "	</select>\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-attachment_description']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='text' name='attachment_description' maxlength='255' value=\"".escape($attachment_description)."\">\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "	<tr>\n";
	echo "		<td colspan='2' align='right'>\n";
	echo "			<br>\n";
	echo "			<input type='submit' class='btn' value='".$text['button-save']."'>\n";
	echo "		</td>\n";
	echo "	</tr>";
	echo "</table>";
	echo "<br><br>";
	echo "</form>";

//include the footer
	require_once "resources/footer.php";

?>