<?php

/**
 * This file is the main file of NVLL; each function starts from here
 * 
 * This file is part of NVLL. NVLL is free software under the terms of the
 * GNU General Public License. You should have received a copy of the license
 * along with NVLL. If not, see <http://www.gnu.org/licenses>.
 */

require_once dirname(__FILE__) . '/common.php';
require_once dirname(__FILE__) . '/functions/captcha.php';

// Remove any attachments from disk and from our session
clear_attachments();

// Reset exception vector
$ev = null;
$remember = NVLL_Request::getStringValue('remember');

// Refresh quota usage
if (!isset($_REQUEST['sort'])) {
    if (NVLL_Session::getQuotaEnable() == true) {
        try {
            $pop = new NVLL_IMAP();
        } catch (Exception $ex) {
            //TODO: Show error without NVLL_Exception!
            $ev = new NVLL_Exception($ex->getMessage());
            require dirname(__FILE__) . '/html/header.php';
            require dirname(__FILE__) . '/html/error.php';
            require dirname(__FILE__) . '/html/footer.php';
            exit;
        }

        $quota = $pop->get_quota_usage($_SESSION['nvll_folder']);
        $_SESSION['quota'] = $quota;
    }
}

// Act on 'service'
$service = NVLL_Request::getStringValue('service');

if ($service == 'logout') {
    require_once dirname(__FILE__) . '/functions/proxy.php';
    header('Location: ' . $conf->base_url . 'logout.php?' . NVLL_Session::getUrlGetSession());
    exit;
}

if ($service == 'inbox_changed') {
    $_SESSION['ajxfolder'] = "INBOX";
    if ($user_prefs->getUseInboxFolder() && strlen($user_prefs->getInboxFolderName()) > 0) $_SESSION['ajxfolder'] = $user_prefs->getInboxFolderName();
}

try {
    $pop = new NVLL_IMAP();
} catch (Exception $ex) {
    if ($service == 'inbox_changed') {
        echo -1;
        unset($_SESSION['ajxfolder']);
        return;
    }
    //TODO: Show error without NVLL_Exception!
    $ev = new NVLL_Exception($ex->getMessage());
    require dirname(__FILE__) . '/html/header.php';
    require dirname(__FILE__) . '/html/error.php';
    require dirname(__FILE__) . '/html/footer.php';
    exit;
}

NVLL_Session::setSendHtmlMail($user_prefs->getSendHtmlMail());

switch ($service) {
        //--------------------------------------------------------------------------------
        // Display a mail...
        //--------------------------------------------------------------------------------
    case 'aff_mail':
        try {
            $attachmentParts = array();
            $content = aff_mail($pop, $_REQUEST['mail'], NVLL_Request::getBoolValue('verbose'), $attachmentParts);

            if ($user_prefs->getCollect() == 2 || $user_prefs->getCollect() == 3) {
                require_once dirname(__FILE__) . '/classes/nvll_contacts.php';

                $path = $conf->prefs_dir . '/' . preg_replace("/(\\\|\/)/", "_", NVLL_Session::getUserKey()) . '.contacts';
                $contacts_object = new NVLL_Contacts();
                $all_to = trim($content['from']) . "; " . trim($content['to']) . "; " . trim($content['cc']) . "; " . trim($content['reply_to']);
                $contacts = $contacts_object->add_contact($path, $all_to);

                if (count($contacts) <= $conf->contact_number_max) $contacts_object->saveList($path, $contacts, $conf, $ev);
                //ignore exception as emails should be send anyways.
            }

            // Display or hide distant HTML images.
            if (!NVLL_Request::getBoolValue('display_images')) $content['body'] = NVLL_Security::disableHtmlImages($content['body']);
            display_embedded_html_images($content, $attachmentParts);

            $hasBody = isset($content['body']) && strlen(trim(preg_replace('/[\x00-\x1F\x7F]/', '', $content['body']))) > 0;
            $bodyIsHTML = $content['body_mime'] === 'text/html';

            // Display as plain text.
            if ($hasBody && $bodyIsHTML && $conf->use_plaintext_by_default && !NVLL_Request::getBoolValue('as_html') && !isset($_REQUEST['user_charset'])) {
                $content['body'] = NVLL_Security::convertHtmlToPlainText($content['body'], $content['body_mime']);
                $content['body'] = htmlspecialchars($content['body'], ENT_COMPAT | ENT_SUBSTITUTE, $charset);
                $content['body'] = NVLL_Body::prepareTextLinks($content['body']);
                $content['body'] = NVLL_Body::addColoredQuotes($content['body']);
                // Replace multiple newlines with a single newline.
                $content['body'] = preg_replace('/(\r?\n){2,}/', "\n\n", $content['body']); // Keep a single blank line between paragraphs.
                // Remove extra whitespace from each line.
                $content['body'] = preg_replace('/^[ \t]+|[ \t]+$/m', '', $content['body']); // Remove leading/trailing spaces per line.
                $content['body'] = trim($content['body']); // Trim leading and trailing whitespace.
                $content['body'] = '<span style="white-space:pre-wrap;white-space:-moz-pre-wrap;white-space:-o-pre-wrap;word-wrap:break-word;">' . $content['body'] . '</span>';
            } else { // Display as HTML.
                $content['body'] = NVLL_Body::prepareHtmlLinks($content['body']);
            }
        } catch (Exception $ex) {
            //TODO: Show error without NVLL_Exception!
            $ev = new NVLL_Exception($ex->getMessage());
            require dirname(__FILE__) . '/html/header.php';
            require dirname(__FILE__) . '/html/error.php';
            require dirname(__FILE__) . '/html/footer.php';
            break;
        }

        $rfc822_hasImages = create_rfc822_content($content, $pop, $attachmentParts);

        // Here we display the message.
        require dirname(__FILE__) . '/html/header.php';
        require dirname(__FILE__) . '/html/menu_mail.php';
        require dirname(__FILE__) . '/html/submenu_mail.php';
        require dirname(__FILE__) . '/html/html_mail.php';
        display_attachments($content, $pop, $attachmentParts);
        require dirname(__FILE__) . '/html/submenu_mail.php';
        require dirname(__FILE__) . '/html/menu_mail.php';
        require dirname(__FILE__) . '/html/script.php';
        require dirname(__FILE__) . '/html/footer.php';

        $pop->close();
        break;

        //--------------------------------------------------------------------------------
        // Compose/Write a mail...
        //--------------------------------------------------------------------------------
    case 'compose':
        if (isset($_SESSION['send_backup']) && $_SESSION['nvll_domain_index'] == $_SESSION['send_backup']['nvll_domain_index']) {
            if (isset($_SESSION['send_backup']['mail_to'])) $mail_to = $_SESSION['send_backup']['mail_to'];
            if (isset($_SESSION['send_backup']['mail_cc'])) $mail_cc = $_SESSION['send_backup']['mail_cc'];
            if (isset($_SESSION['send_backup']['mail_bcc'])) $mail_bcc = $_SESSION['send_backup']['mail_bcc'];
            if (isset($_SESSION['send_backup']['mail_subject'])) $mail_subject = $_SESSION['send_backup']['mail_subject'];
            if (isset($_SESSION['send_backup']['mail_body'])) $mail_body = $_SESSION['send_backup']['mail_body'];
            if (isset($_SESSION['send_backup']['mail_attachment'])) $_SESSION['nvll_attach_array'] = $_SESSION['send_backup']['mail_attachment'];
            if (isset($_SESSION['send_backup']['mail_receipt'])) $mail_receipt = $_SESSION['send_backup']['mail_receipt'];
            if (isset($_SESSION['send_backup']['mail_priority'])) $mail_priority = $_SESSION['send_backup']['mail_priority'];

            unset($_SESSION['send_backup']);
        }

        if (isset($_REQUEST['mail_to']) && $_REQUEST['mail_to'] != "") $mail_to = $_REQUEST['mail_to'];

        $pop->close();
        // Add signature
        add_signature($mail_body);

        require dirname(__FILE__) . '/html/header.php';
        require dirname(__FILE__) . '/html/menu_inbox.php';
        require dirname(__FILE__) . '/html/compose.php';
        require dirname(__FILE__) . '/html/menu_inbox.php';
        require dirname(__FILE__) . '/html/script.php';
        require dirname(__FILE__) . '/html/footer.php';
        break;

        //--------------------------------------------------------------------------------
        // Reply (all) on a mail...
        //--------------------------------------------------------------------------------
    case 'reply':
    case 'reply_all':
        if (isset($_SESSION['send_backup']) && $_SESSION['nvll_domain_index'] == $_SESSION['send_backup']['nvll_domain_index']) unset($_SESSION['send_backup']);

        clear_attachments();

        $attachmentParts = array();
        try {
            $content = aff_mail($pop, $_REQUEST['mail'], NVLL_Request::getBoolValue('verbose'), $attachmentParts);
            if (!NVLL_Request::getBoolValue('display_images')) $content['body'] = NVLL_Security::disableHtmlImages($content['body']);
            display_embedded_html_images($content, $attachmentParts);
        } catch (Exception $ex) {
            //TODO: Show error without NVLL_Exception!
            $ev = new NVLL_Exception($ex->getMessage());
            require dirname(__FILE__) . '/html/header.php';
            require dirname(__FILE__) . '/html/error.php';
            require dirname(__FILE__) . '/html/footer.php';
            break;
        }

        $mail_messageid = urlencode($content['message_id']);

        if ($service == 'reply') { // if reply...
            $mail_to = !empty($content['reply_to']) ? $content['reply_to'] : $content['from'];
        } else { //if reply all...
            $cc = "";
            if (!empty($content['reply_to'])) {
                $mail_to = get_reply_all($content['reply_to'], $content['to'], $cc);
            } else {
                $mail_to = get_reply_all($content['from'], $content['to'], $cc);
            }
            $mail_cc = $content['cc'];
        }

        $mail_subject = add_reply_to_subject($content['subject']);
        // Add quoting
        add_quoting($mail_body, $content);
        // Add signature
        add_signature($mail_body);

        require dirname(__FILE__) . '/html/header.php';
        require dirname(__FILE__) . '/html/menu_inbox.php';
        require dirname(__FILE__) . '/html/compose.php';
        require dirname(__FILE__) . '/html/menu_inbox.php';
        require dirname(__FILE__) . '/html/script.php';
        require dirname(__FILE__) . '/html/footer.php';

        $pop->close();
        break;

        //--------------------------------------------------------------------------------
        // Forward a mail...
        //--------------------------------------------------------------------------------
    case 'forward':
        if (isset($_SESSION['send_backup']) && $_SESSION['nvll_domain_index'] == $_SESSION['send_backup']['nvll_domain_index']) unset($_SESSION['send_backup']);

        clear_attachments();

        $mail_list = explode('$', $_REQUEST['mail']);
        $mail_body = '';

        for ($mail_num = 0; $mail_num < count($mail_list); $mail_num++) {
            try {
                $content = aff_mail($pop, $mail_list[$mail_num], NVLL_Request::getBoolValue('verbose'));
            } catch (Exception $ex) {
                //TODO: Show error without NVLL_Exception!
                $ev = new NVLL_Exception($ex->getMessage());
                require dirname(__FILE__) . '/html/header.php';
                require dirname(__FILE__) . '/html/error.php';
                require dirname(__FILE__) . '/html/footer.php';
                break;
            }

            if (count($mail_list) == 1) {
                $mail_subject = $html_forward_short . ' ' . $content['subject'];
            } else {
                $mail_subject = 'Fwd: ';
            }

            if (isset($conf->broken_forwarding) && $conf->broken_forwarding) {
                // Set body
                // DEPRECATED // TODO: Put to own function and merge with code from add_quoting()!
                // if ($user_pref->getOutlookQuoting()) {
                //     $mail_body .= $original_msg . $conf->crlf . $html_from_label . ' ' . $content['from'] . $conf->crlf
                //         . $html_to_label . ' ' . $content['to'] . $conf->crlf . $html_sent_label . ' ' . $content['complete_date']
                //         . $conf->crlf . $html_subject_label . ' ' . $content['subject'] . $conf->crlf . $conf->crlf
                //         . strip_tags2($content['body'], '') . $conf->crlf . $conf->crlf;
                // } else {
                //     $stripped_content = strip_tags2($content['body'], '');
                //     $mail_body .= mailquote($stripped_content, $content['from'], $html_wrote, $content['body_mime']) . $conf->crlf . $conf->crlf;
                // }

                $content['body'] = NVLL_Security::convertHtmlToPlainText($content['body'], $content['body_mime']);
                $content['body'] = preg_replace('/(\r?\n){2,}/', "\n\n", $content['body']); // Keep a single blank line between paragraphs.
                $content['body'] = preg_replace('/^[ \t]+|[ \t]+$/m', '', $content['body']); // Remove leading/trailing spaces per line.
                $content['body'] = trim($content['body']);

                // TODO: Include attachments if the message to be forwarded has them!
                $mail_body .= '-------------------- ' . $html_original_msg . ' --------------------' . $conf->crlf
                    . $html_from_label . ' ' . $content['from'] . $conf->crlf
                    . $html_to_label . ' ' . $content['to'] . $conf->crlf
                    . $html_date_label . ' ' . $content['complete_date'] . $conf->crlf
                    . $html_subject_label . ' ' . $content['subject'] . $conf->crlf
                    . "----------------------------------------------------------" . $conf->crlf
                    . $conf->crlf
                    . $content['body'] . $conf->crlf;

                $broken_forwarding = true;
            } else {
                $broken_forwarding = false;
            }
        }

        // Let compose.php know to attach the original message
        $forward_msgnum = $_REQUEST['mail'];

        // Add signature
        add_signature($mail_body);

        require dirname(__FILE__) . '/html/header.php';
        require dirname(__FILE__) . '/html/menu_inbox.php';
        require dirname(__FILE__) . '/html/compose.php';
        require dirname(__FILE__) . '/html/menu_inbox.php';
        require dirname(__FILE__) . '/html/script.php';
        require dirname(__FILE__) . '/html/footer.php';

        $pop->close();
        break;

        //--------------------------------------------------------------------------------
        // Manage folders...
        //--------------------------------------------------------------------------------
    case 'managefolders':
        $do = NVLL_Request::getStringValue('do');
        $dl = convertLang2Html($html_down_mail);
        switch ($do) {
            case 'create_folder':
                if ($_REQUEST['createnewbox'] && $pop->createmailbox($_REQUEST['createnewbox'])) $pop->subscribe($_REQUEST['createnewbox'], true);
                break;
            case 'subscribe_folder':
                if ($_REQUEST['subscribenewbox']) $pop->subscribe($_REQUEST['subscribenewbox'], false);
                break;
            case 'remove_folder':
                if ($_REQUEST['removeoldbox']) {
                    // Don't want to remove, just unsubscribe.
                    //$pop->deletemailbox($removeoldbox, $ev);
                    //if(NVLL_Exception::isException($ev)) break ;
                    $pop->unsubscribe($_REQUEST['removeoldbox']);
                }
                break;
            case 'rename_folder':
                if ($_REQUEST['renamenewbox'] && $_REQUEST['renameoldbox']) {
                    if ($pop->renamemailbox($_REQUEST['renameoldbox'], $_REQUEST['renamenewbox'])) {
                        $pop->unsubscribe($_REQUEST['renameoldbox']);
                        $pop->subscribe($_REQUEST['renamenewbox'], true);
                    }
                }
                break;
            case 'download_folder':
                if ($_REQUEST['downloadbox']) {
                    $pop->downloadmailbox($_REQUEST['downloadbox'], $ev);
                    if (NVLL_Exception::isException($ev)) break;
                }
                break;
            case $dl:
                $pop->downloadtmpfile($ev);
                if (NVLL_Exception::isException($ev)) break;
                break;
            case 'delete_folder':
                if ($_REQUEST['deletebox']) {
                    $pop->unsubscribe($_REQUEST['deletebox']);
                    $pop->deletemailbox($_REQUEST['deletebox']);
                }
                break;
        }

        require dirname(__FILE__) . '/html/header.php';
        require dirname(__FILE__) . '/html/menu_inbox.php';
        if ($pop->is_imap()) require dirname(__FILE__) . '/html/folders.php';
        require dirname(__FILE__) . '/html/menu_inbox.php';
        require dirname(__FILE__) . '/html/script.php';
        require dirname(__FILE__) . '/html/footer.php';

        $pop->close();

        break;

        //--------------------------------------------------------------------------------
        // Manage filters...
        //--------------------------------------------------------------------------------
    case 'filters':
        $user_key = NVLL_Session::getUserKey();
        $filterset = NVLL_UserFilters::read($user_key, $ev);

        if (NVLL_Exception::isException($ev)) {
            require dirname(__FILE__) . '/html/header.php';
            require dirname(__FILE__) . '/html/error.php';
            require dirname(__FILE__) . '/html/footer.php';
            break;
        }

        if (isset($_REQUEST['do'])) {
            switch (trim($_REQUEST['do'])) {
                case 'delete':
                    if ($_REQUEST['filter']) {
                        unset($filterset->filterset[$_REQUEST['filter']]);
                        $filterset->dirty_flag = 1;
                        $filterset->commit($ev);
                        if (NVLL_Exception::isException($ev)) {
                            require dirname(__FILE__) . '/html/header.php';
                            require dirname(__FILE__) . '/html/error.php';
                            require dirname(__FILE__) . '/html/footer.php';
                            break;
                        }
                    }
                    break;

                case 'create':
                    if (!$_REQUEST['filtername']) break;
                    if ($_REQUEST['thing1'] == '-') {
                        break;
                    } else {
                        $filterset->filterset[$_REQUEST['filtername']]['SEARCH'] = $_REQUEST['thing1'] . ' "' . $_REQUEST['contains1'] . '"';
                    }
                    if ($_REQUEST['thing2'] != '-') $filterset->filterset[$_REQUEST['filtername']]['SEARCH'] .= ' ' . $_REQUEST['thing2'] . ' "' . $_REQUEST['contains2'] . '"';
                    if ($_REQUEST['thing3'] != '-') $filterset->filterset[$_REQUEST['filtername']]['SEARCH'] .= ' ' . $_REQUEST['thing3'] . ' "' . $_REQUEST['contains3'] . '"';
                    if ($_REQUEST['filter_action'] == 'DELETE') {
                        $filterset->filterset[$_REQUEST['filtername']]['ACTION'] = 'DELETE';
                    } elseif ($_REQUEST['filter_action'] == 'MOVE') {
                        $filterset->filterset[$_REQUEST['filtername']]['ACTION'] = 'MOVE:' . $_REQUEST['filter_move_box'];
                    } else {
                        break;
                    }

                    $filterset->dirty_flag = 1;
                    $filterset->commit($ev);

                    if (NVLL_Exception::isException($ev)) {
                        require dirname(__FILE__) . '/html/header.php';
                        require dirname(__FILE__) . '/html/error.php';
                        require dirname(__FILE__) . '/html/footer.php';
                        break;
                    }
                    break;
            }
        }

        $html_filter_select = $filterset->html_filter_select();
        $filter_move_to = $pop->html_folder_select('filter_move_box', '');

        require dirname(__FILE__) . '/html/header.php';
        require dirname(__FILE__) . '/html/menu_prefs.php';
        require dirname(__FILE__) . '/html/submenu_prefs.php';
        require dirname(__FILE__) . '/html/filter_prefs.php';
        require dirname(__FILE__) . '/html/submenu_prefs.php';
        require dirname(__FILE__) . '/html/menu_prefs.php';
        require dirname(__FILE__) . '/html/script.php';
        require dirname(__FILE__) . '/html/footer.php';

        $pop->close();

        break;

        //--------------------------------------------------------------------------------
        // Set preferences...
        //--------------------------------------------------------------------------------
    case 'setprefs':
        //TODO: Move all isset() to if()!
        if (isset($_REQUEST['submit_prefs'])) {
            if (isset($_REQUEST['full_name'])) $user_prefs->setFullName(NVLL_Request::getStringValue('full_name'));
            if (isset($_REQUEST['msg_per_page'])) $user_prefs->msg_per_page = $_REQUEST['msg_per_page'];
            if (isset($_REQUEST['email_address'])) $user_prefs->setEmailAddress(NVLL_Request::getStringValue('email_address'));

            $user_prefs->setBccSelf(isset($_REQUEST['cc_self']));
            $user_prefs->setHideAddresses(isset($_REQUEST['hide_addresses']));
            $user_prefs->setShowAlert(isset($_REQUEST['show_alert']));
            $user_prefs->setOutlookQuoting(isset($_REQUEST['outlook_quoting']));
            $user_prefs->setUseJavaScript(isset($_REQUEST['use_javascript']));
            $user_prefs->setColoredQuotes(isset($_REQUEST['colored_quotes']));
            $user_prefs->setDisplayStructuredText(isset($_REQUEST['display_struct']));
            $user_prefs->seperate_msg_win = isset($_REQUEST['seperate_msg_win']);

            if (isset($_REQUEST['reply_leadin'])) $user_prefs->reply_leadin = NVLL_Request::getStringValue('reply_leadin');
            if (isset($_REQUEST['signature'])) {
                if (NVLL_Request::getBoolValue('html_mail_send')) {
                    $user_prefs->setSignature($_REQUEST['signature']);
                } else {
                    $user_prefs->setSignature(NVLL_Request::getStringValue('signature'));
                }
            }
            if (isset($_REQUEST['wrap_msg'])) $user_prefs->setWrapMessages($_REQUEST['wrap_msg']);

            $user_prefs->setUseSignatureSeparator(isset($_REQUEST['sig_sep']));
            $user_prefs->setSendHtmlMail(isset($_REQUEST['html_mail_send']));
            $user_prefs->setUseGraphicalSmilies(isset($_REQUEST['graphical_smilies']));
            $user_prefs->setUseSentFolder(isset($_REQUEST['sent_folder']));

            if (isset($_REQUEST['sent_folder_name'])) {
                $replace = str_replace($_SESSION['imap_namespace'], "", $_REQUEST['sent_folder_name']);
                $user_prefs->setSentFolderName(safestrip($replace));
            }

            $user_prefs->setUseTrashFolder(isset($_REQUEST['trash_folder']));

            if (isset($_REQUEST['trash_folder_name'])) {
                $replace = str_replace($_SESSION['imap_namespace'], "", $_REQUEST['trash_folder_name']);
                $user_prefs->setTrashFolderName(safestrip($replace));
            }

            $user_prefs->setUseInboxFolder(isset($_REQUEST['inbox_folder']));

            if (isset($_REQUEST['inbox_folder_name'])) {
                $replace = str_replace($_SESSION['imap_namespace'], "", $_REQUEST['inbox_folder_name']);
                $user_prefs->setInboxFolderName(safestrip($replace));
            }

            if (isset($_REQUEST['collect'])) $user_prefs->setCollect($_REQUEST['collect']);
            if (isset($_REQUEST['lang'])) $user_prefs->lang = $_REQUEST['lang'];
            if (isset($_REQUEST['theme'])) {
                $user_prefs->theme = $_REQUEST['theme'];
                if ($_REQUEST['theme'] == "default") {
                    $_SESSION['nvll_theme'] = $conf->default_theme;
                } else {
                    $_SESSION['nvll_theme'] = $_REQUEST['theme'];
                }
            }

            if ($conf->prefs_dir) {
                // Commit preferences
                $user_prefs->commit($ev);
            } else {
                // Validate preferences
                $user_prefs->validate($ev);
            }

            if (NVLL_Exception::isException($ev)) {
                require dirname(__FILE__) . '/html/header.php';
                require dirname(__FILE__) . '/html/error.php';
                require dirname(__FILE__) . '/html/footer.php';
                break;
            }

            NVLL_Session::setUserPrefs($user_prefs);
        }

        require dirname(__FILE__) . '/html/header.php';
        require dirname(__FILE__) . '/html/menu_prefs.php';
        require dirname(__FILE__) . '/html/submenu_prefs.php';
        require dirname(__FILE__) . '/html/prefs.php';
        require dirname(__FILE__) . '/html/submenu_prefs.php';
        require dirname(__FILE__) . '/html/menu_prefs.php';
        require dirname(__FILE__) . '/html/script.php';
        require dirname(__FILE__) . '/html/footer.php';

        $pop->close();

        break;

        //--------------------------------------------------------------------------------
        // Login...
        //--------------------------------------------------------------------------------
    default:
        if ($service == 'login') {
            if ($conf->use_captcha && !verify_captcha($_REQUEST['challenge'], $_REQUEST['captcha'])) {
                $ev = new NVLL_Exception('Invalid Captcha');
                require dirname(__FILE__) . '/html/header.php';
                require dirname(__FILE__) . '/html/error.php';
                require dirname(__FILE__) . '/html/footer.php';
                break;
            }

            // Subscribe to INBOX, Sent and Trash. This is useful if it's not already done.
            if ($pop->is_imap() && $conf->use_default_folders) {
                $folders_to_check = array($conf->default_inbox_folder, $conf->default_sent_folder, $conf->default_trash_folder);
                $existing_folders = $pop->getsubscribednames();

                foreach ($folders_to_check as $folder) {
                    if (!in_array($folder, $existing_folders)) {
                        if ($pop->createmailbox($folder)) {
                            $pop->subscribe($folder, true);
                        } elseif ($_SESSION['nvll_folder'] == $folder) {
                            // Subscribe to INBOX.
                            $pop->subscribe($_SESSION['nvll_folder'], false);
                        } else {
                            error_log("NVLL: Error creating folder '$folder': " . $pop->last_error());
                        }
                    }
                }
            }
        }

        // We may need to apply some filters to the INBOX...  this is still a work in progress.
        if (!isset($_REQUEST['sort'])) {
            if ($pop->is_imap()) {
                if ($_SESSION['nvll_folder'] == 'INBOX') {
                    $user_key = NVLL_Session::getUserKey();
                    if (!empty($conf->prefs_dir)) {
                        $filters = NVLL_UserFilters::read($user_key, $ev);
                        if (NVLL_Exception::isException($ev)) {
                            error_log("NVLL: Error reading filters for user '$user_key': " . $ev->getMessage());
                            $filters = null;
                            $ev = null;
                        }

                        $small_search = 'unseen ';
                        if (NVLL_Request::getBoolValue('reapply_filters')) $small_search = '';
                        if ($filters != null) {
                            foreach ($filters->filterset as $name => $filter) {
                                //new filter criteria must be implemented in $pop->search(...) function!!!
                                $filter_messages = $pop->search($small_search . $filter['SEARCH']);
                                $filter_to_folder = array();
                                foreach ($filter_messages as $filt_msg_no) {
                                    if ($filter['ACTION'] == 'DELETE') {
                                        $pop->delete($filt_msg_no);
                                    } elseif (preg_match("/^MOVE:(.+)$/", $filter['ACTION'], $filter_to_folder)) {
                                        $pop->mail_move($filt_msg_no, $filter_to_folder[1]);
                                    }
                                }
                            }
                        }
                    }
                    if (!$pop->expunge()) error_log("NVLL: Error expunging mail for user '$user_key': " . $pop->last_error());
                }
            }
        }

        // If we get this far, consider ourselves logged in
        $_SESSION['nvll_loggedin'] = 1;
        //if( NVLL_Session::rename_session() ) {
        $new_session_name = NVLL_Session::rename_session();
        if (strlen($new_session_name) > 0) {
            NVLL_Session::save_session();
            if (NVLL_Exception::isException($ev)) {
                require dirname(__FILE__) . '/html/header.php';
                require dirname(__FILE__) . '/html/error.php';
                require dirname(__FILE__) . '/html/footer.php';
                break;
            }

            NVLL_Session::createCookie($remember);
            if (isset($_SESSION['send_backup']) && $_SESSION['nvll_domain_index'] == $_SESSION['send_backup']['nvll_domain_index']) {
                //header("Location: ".$conf->base_url."api.php?".NVLL_Session::getUrlGetSession().'&service=compose');
                header("Location: " . $conf->base_url . "api.php?_vmbox=" . $new_session_name . '&service=compose');
            } else {
                //header("Location: ".$conf->base_url."api.php?".NVLL_Session::getUrlGetSession());
                header("Location: " . $conf->base_url . "api.php?_vmbox=" . $new_session_name);
            }
            exit();
        }

        // Fetch message list
        $tab_mail = array();
        $skip = 0;
        $num_msg = $pop->num_msg();

        if ($service == 'inbox_changed') {
            $req_num_msg = 0;

            if (isset($_REQUEST['num_msg'])) $req_num_msg = intval($_REQUEST['num_msg']);
            if ($num_msg != $req_num_msg) $_SESSION['inbox_alert'] = false;

            echo $num_msg;
            unset($_SESSION['ajxfolder']);
            $pop->close();
            return;
        }

        if ($_SESSION['nvll_folder'] == 'INBOX') {
            $_SESSION['inbox_num_msg'] = $num_msg;
            $_SESSION['inbox_alert'] = true;
        }
        if (isset($_REQUEST['skip'])) $skip = $_REQUEST['skip'];
        if ($num_msg > 0) {
            //TODO: Remove later try/catch block!
            try {
                $tab_mail = inbox($pop, $skip);
            } catch (Exception $ex) {
                $ev = new NVLL_Exception($ex->getMessage());
                require dirname(__FILE__) . '/html/header.php';
                require dirname(__FILE__) . '/html/error.php';
                require dirname(__FILE__) . '/html/footer.php';
                break;
            }
        }

        require dirname(__FILE__) . '/html/header.php';
        require dirname(__FILE__) . '/html/menu_inbox.php';
        require dirname(__FILE__) . '/html/html_top_table.php';
        if (count($tab_mail) < 1) {
            // the mailbox is empty
            include './html/no_mail.php';
        } else {
            // there are messages, we display
            while ($tmp = array_shift($tab_mail)) {
                require dirname(__FILE__) . '/html/html_inbox.php';
            }
        }

        $list_of_folders = '';
        // If we show it twice, the bottom folder select is sent, and might be
        // wrong.
        if ($pop->is_imap()) {
            if (isset($_REQUEST['sort'])) {
                $subscribed = $_SESSION['subscribed'];
            } else {
                try {
                    // gather list of folders for menu_inbox_status
                    $subscribed = $pop->getsubscribed();
                    $_SESSION['subscribed'] = $subscribed;
                } catch (Exception $ex) {
                    //TODO: Show error without NVLL_Exception!
                    $ev = new NVLL_Exception($ex->getMessage());
                    require dirname(__FILE__) . '/html/header.php';
                    require dirname(__FILE__) . '/html/error.php';
                    require dirname(__FILE__) . '/html/footer.php';
                    break;
                }
            }
            $list_of_folders = set_list_of_folders($pop, $subscribed);
        }

        require dirname(__FILE__) . '/html/html_bottom_table.php';
        require dirname(__FILE__) . '/html/menu_inbox.php';
        require dirname(__FILE__) . '/html/script.php';
        require dirname(__FILE__) . '/html/footer.php';

        $pop->close();

        break;
}

/**
 * Display attached RFC822 Message (e.g. .eml attachment)
 * @param string content filled with rfc822 content
 * @param NVLL_IMAP $pop
 * @param NVLL_MailPart $attachmentPart RFC822 Attachment part
 * @param string name attachment name
 * @param string header attachment header
 * @param string body attachment body
 * @param string partNumber attachment partNumber
 */
function display_rfc822(&$content, $pop, $attachmentPart, $name = '', $header = '', $body = '', $partNumber = '')
{
    global $conf;
    global $html_subject_label, $html_from_label, $html_date_label, $html_to_label;

    $lang = $_SESSION['nvll_lang'];
    $partStructure = $attachmentPart->getPartStructure();
    $parts_info = $partStructure->getPartsInfo();

    if ($name == '') $name = $partStructure->getName($name);
    if ($partNumber == '') $partNumber = $attachmentPart->getPartNumber();

    $mimeID = "";
    $body_mime = $attachmentPart->getInternetMediaType()->__toString();
    $encoding = $partStructure->getEncoding()->__toString();

    if ($partStructure->getInternetMediaType()->isRfc822Message() && $conf->display_text_attach) {
        //$header=$pop->fetchbody($_REQUEST['mail'],$partNumber.'.0',$partNumber,true,true);
        $header = $pop->fetchbody($_REQUEST['mail'], $partNumber . '.0', $mimeID, true, true);
    }

    if ($partStructure->getInternetMediaType()->isHtmlText() || $partStructure->getInternetMediaType()->isPlainText()) {
        $body = $pop->fetchbody($_REQUEST['mail'], $partNumber, $mimeID, false, true);
        $charset = $partStructure->getCharset();
        $header = $pop->parse_headers($header);
        $body = NVLL_IMAP::decode($body, $encoding);
        $charset = detect_body_charset($body, $charset);

        if (isset($_REQUEST['user_charset']) && $_REQUEST['user_charset'] != '') $charset = $_REQUEST['user_charset'];

        $body = remove_stuff($body, $body_mime, $charset);
        $body = os_iconv($charset, 'UTF-8', $body);
        $body = graphicalsmilies($body);
        $content = $content . $name . '<hr class="mailAttachSep" />';
        $content = $content . '<div class="mailTextAttach">';
        $subject = $header->subject;
        $charset = detect_body_charset($subject, 'default');
        $match = array();

        if (preg_match('/^=\?(.*?)\?/', $subject, $match)) {
            $charset = $match[1];
            $subject = iconv_mime_decode($subject, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, $charset);
        }

        if (isset($_REQUEST['user_charset']) && $_REQUEST['user_charset'] != '') $charset = $_REQUEST['user_charset'];

        $subject = os_iconv($charset, 'UTF-8', $subject);
        $content = $content . $html_subject_label . " " . $subject . '<br />';
        $from = imap_rfc822_write_address($header->from[0]->mailbox, $header->from[0]->host, $header->from[0]->personal);
        // $from = $pop->write_address($header->from[0]->mailbox, $header->from[0]->host, $header->from[0]->personal);
        $charset = detect_body_charset($from, 'default');
        $match = array();

        if (preg_match('/^=\?(.*?)\?/', $from, $match)) {
            $charset = $match[1];
            $from = iconv_mime_decode($from, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, $charset);
        }

        if (isset($_REQUEST['user_charset']) && $_REQUEST['user_charset'] != '') $charset = $_REQUEST['user_charset'];

        $from = os_iconv($charset, 'UTF-8', $from);
        $from = htmlentities($from, ENT_COMPAT, 'UTF-8');
        $content = $content . $html_from_label . " " . $from . '<br />';
        $date = strtotime($header->date);
        $content = $content . $html_date_label . " " . format_date($date, $lang) . ' ' . format_time($date, $lang) . '<br />';
        $to = imap_rfc822_write_address($header->to[0]->mailbox, $header->to[0]->host, $header->to[0]->personal);
        // $to = $pop->write_address($header->to[0]->mailbox, $header->to[0]->host, $header->to[0]->personal);
        $charset = detect_body_charset($to, 'default');
        $match = array();

        if (preg_match('/^=\?(.*?)\?/', $to, $match)) {
            $charset = $match[1];
            $to = iconv_mime_decode($to, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, $charset);
        }

        if (isset($_REQUEST['user_charset']) && $_REQUEST['user_charset'] != '') $charset = $_REQUEST['user_charset'];

        $to = os_iconv($charset, 'UTF-8', $to);
        $to = htmlentities($to, ENT_COMPAT, 'UTF-8');
        $content = $content . $html_to_label . " " . $to . '<br />';
        $content = $content . '<br />';
        $content = $content . $body;
        $content = $content . '</div> <!-- .mailTextAttach -->';
    } elseif ($partStructure->hasParts()) {
        $parts = $partStructure->getParts();
        $body_index = -1;

        for ($i = 0; $i < count($parts); $i++) {
            $bodyPartStructure = new NVLL_MailStructure($parts[$i], $parts_info);
            if (!$bodyPartStructure->isAttachment() && $bodyPartStructure->getInternetMediaType()->isHtmlText()) $body_index = $i;
        }

        if ($body_index == -1) {
            for ($i = 0; $i < count($parts); $i++) {
                $bodyPartStructure = new NVLL_MailStructure($parts[$i], $parts_info);
                if (!$bodyPartStructure->isAttachment() && $bodyPartStructure->getInternetMediaType()->isPlainText()) $body_index = $i;
            }
        }

        if ($body_index >= 0) {
            $bodyPartStructure = new NVLL_MailStructure($parts[$body_index], $parts_info);
            $part = new NVLL_MailPart($bodyPartStructure, $body_index);
            display_rfc822($content, $pop, $part, $name, $header, $body, $partNumber . '.' . ($body_index + 1));
        } else {
            for ($i = 0; $i < count($parts); $i++) {
                $bodyPartStructure = new NVLL_MailStructure($parts[$i], $parts_info);
                $part = new NVLL_MailPart($bodyPartStructure, $i);
                if (
                    $bodyPartStructure->getInternetMediaType()->__toString() == 'multipart/mixed' ||
                    $bodyPartStructure->getInternetMediaType()->__toString() == 'multipart/related' ||
                    $bodyPartStructure->getInternetMediaType()->__toString() == 'multipart/signed' ||
                    0
                ) {
                    display_rfc822($content, $pop, $part, $name, $header, $body, $partNumber);
                } else {
                    display_rfc822($content, $pop, $part, $name, $header, $body, $partNumber . '.' . ($i + 1));
                }
            }
        }
    }
    return;
}

/**
 * Display attachments
 * @param NVLL_IMAP $pop
 * @param array $attachmentParts Attachment parts
 */
function create_rfc822_content(&$content, $pop, $attachmentParts)
{
    global $conf;

    $rfc822_hasImages = false;
    $name = '';

    //foreach ($attachmentParts as $attachmentPart) { //for all rfc822 parts...
    for ($i = count($attachmentParts) - 1; $i >= 0; $i--) { //for all rfc822 parts...
        $attachmentPart = $attachmentParts[$i];
        $partStructure = $attachmentPart->getPartStructure();
        $name = $partStructure->getName($name);
        if ($partStructure->getInternetMediaType()->isRfc822Message() && $conf->display_text_attach) {
            $rfc822_content = "";
            display_rfc822($rfc822_content, $pop, $attachmentPart);
            $rfc822_content_array = array('body' => $rfc822_content);
            display_embedded_html_images($rfc822_content_array, $attachmentParts);
            // Display or hide distant HTML images
            if (!NVLL_Request::getBoolValue('display_images')) {
                $rfc822_content_array['body'] = NVLL_Security::disableHtmlImages($rfc822_content_array['body']);
                $rfc822_hasImages = NVLL_Security::hasDisabledHtmlImages($rfc822_content_array['body']);
            }
            $content['rfc822'] = $rfc822_content_array['body'];
        }
    }
    return $rfc822_hasImages;
}


function display_attachments($content, $pop, $attachmentParts)
{
    global $conf;

    //TODO: Use "mailData" DIV from file "html/html_mail.php"!
    echo '<div class="mailData">';

    if (isset($content['rfc822'])) echo $content['rfc822'];

    $name = '';
    foreach ($attachmentParts as $attachmentPart) { //for all attached txt or image parts...
        $partStructure = $attachmentPart->getPartStructure();
        $name = $partStructure->getName($name);
        if ($partStructure->getInternetMediaType()->isPlainText() && $conf->display_text_attach) { //if plain text...
            echo $name . '<hr class="mailAttachSep" />';
            echo '<div class="mailTextAttach">';
            //TODO: Replace URLs and Smilies in text/plain attachment?
            echo view_part($pop, $_REQUEST['mail'], $attachmentPart->getPartNumber(), $attachmentPart->getEncoding()->__toString(), $partStructure->getCharset());
            echo '</div> <!-- .mailTextAttach -->';
        } elseif ($partStructure->getInternetMediaType()->isImage() && $partStructure->isAttachment() && $conf->display_img_attach) { //if attached image...
            $imageType = $attachmentPart->getInternetMediaType()->__toString();
            if (NVLL_Security::isSupportedImageType($imageType)) {
                echo $name . '<hr class="mailAttachSep" />';
                echo '<div class="mailImgAttach">';
                echo '<img src="get_img.php?' . NVLL_Session::getUrlGetSession() . '&mail=' . $_REQUEST['mail'] . '&num=' . $attachmentPart->getPartNumber() . '&mime='
                    . $imageType . '&transfer=' . $attachmentPart->getEncoding()->__toString() . '" alt="" title="' . $partStructure->getName() . '" />';
                echo '</div> <!-- .mailImgAttach -->';
            }
        }
    }
    echo '</div> <!-- .mailData -->';
}

/**
 * Display embedded HTML images
 * @param array $content Content
 * @param array $attachmentParts Attachment parts
 */
function display_embedded_html_images(&$content, $attachmentParts)
{
    global $conf;

    foreach ($attachmentParts as $attachmentPart) { //for all attachment parts...
        $partStructure = $attachmentPart->getPartStructure();
        if ($partStructure->getInternetMediaType()->isImage() && ! $partStructure->isAttachment() && $conf->display_img_attach) { //if embedded image...
            $imageType = $attachmentPart->getInternetMediaType()->__toString();
            if (NVLL_Security::isSupportedImageType($imageType)) {
                $new_img_src = 'get_img.php?' . NVLL_Session::getUrlGetSession() . '&mail=' . $_REQUEST['mail'] . '&num=' . $attachmentPart->getPartNumber() . '&mime=' . $imageType . '&transfer=' . $attachmentPart->getEncoding()->__toString();
                $img_id = 'cid:' . trim($partStructure->getId(true), '<>');
                $content['body'] = str_replace('[' . $img_id . ']', '<img src="' . $new_img_src . '" alt="" title="' . $partStructure->getName() . '" />', $content['body']);
                $content['body'] = str_replace($img_id, $new_img_src, $content['body']);
            }
        }
    }
}

function add_signature(&$body)
{
    $user_prefs = NVLL_Session::getUserPrefs();
    if ($user_prefs->getSignature() != '') {
        // Add signature with separation if needed
        //TODO: Really add separator if HTML mail?
        if ($user_prefs->getUseSignatureSeparator()) {
            $body .= "\r\n\r\n" . "-- \r\n" . $user_prefs->getSignature();
        } else {
            $body .= "\r\n\r\n" . $user_prefs->getSignature();
        }
    }
}

function add_quoting(&$mail_body, $content)
{
    global $user_prefs, $conf;
    global $html_original_msg, $html_from_label, $html_to_label, $html_date_label, $html_subject_label;
    global $html_quote_wrote;

    $from = $content['from'];
    $to = $content['to'];

    if ($user_prefs->getSendHtmlMail()) {
        $stripped_content = $content['body'];

        if (strtolower($content['body_mime']) == "text/plain") {
            $stripped_content = preg_replace("/<br \/>\s*\n/i", "\n", $stripped_content);
            $stripped_content = preg_replace("/<br \/>\s*\r/i", "\r", $stripped_content);
            $stripped_content = preg_replace("/<br \/>\s*\r\n/i", "\r\n", $stripped_content);
            $stripped_content = preg_replace("/\r\n/i", "<br />", $stripped_content);
            $stripped_content = preg_replace("/\n/i", "<br />", $stripped_content);
            $stripped_content = preg_replace("/\r/i", "<br />", $stripped_content);
        }

        $from = preg_replace("/</", "&lt;", $from);
        $from = preg_replace("/>/", "&gt;", $from);
        $to = preg_replace("/</", "&lt;", $to);
        $to = preg_replace("/>/", "&gt;", $to);
        $crlf = "<br />\r\n";
    } else {
        $crlf = "\r\n";
        $body = $content['body'];
        $stripped_content = NVLL_Security::convertHtmlToPlainText($body, $content['body_mime']);
        $stripped_content = preg_replace('/(\r?\n){2,}/', "\n\n", $stripped_content); // Keep a single blank line between paragraphs.
        $stripped_content = preg_replace('/^[ \t]+|[ \t]+$/m', '', $stripped_content); // Remove leading/trailing spaces per line.
        $stripped_content = trim($stripped_content);
    }

    if ($user_prefs->getOutlookQuoting()) {
        $mail_body = '-------------------- ' . $html_original_msg . ' --------------------' . $crlf
            . $html_from_label . ' ' . $from . $crlf
            . $html_to_label . ' ' . $to . $crlf
            . $html_date_label . ' ' . $content['complete_date'] . $crlf
            . $html_subject_label . ' ' . $content['subject'] . $crlf
            . "----------------------------------------------------------" . $crlf
            . $crlf
            . $stripped_content;
    } else {
        if (isset($conf->enable_reply_leadin) && $conf->enable_reply_leadin == true && isset($user_prefs->reply_leadin) && ($user_prefs->reply_leadin != '')) {
            $parsed_leadin = NVLL_UserPrefs::parseLeadin($user_prefs->reply_leadin, $content);
            $mail_body = mailquote($stripped_content, $parsed_leadin, '', $content['body_mime']);
        } else {
            $mail_body = mailquote($stripped_content, '', '', $content['body_mime']);
            $mail_body = sprintf($html_quote_wrote, $content['date'], $content['time'], $from) . $crlf . $mail_body;
        }
    }
}

function add_reply_to_subject($subject)
{
    global $html_reply_short;

    $subjectStart = substr($subject, 0, strlen($html_reply_short));
    //if NOT start with localized "Re:" ...
    if (strcasecmp($subjectStart, $html_reply_short) != 0) return $html_reply_short . ' ' . $subject;
    return $subject;
}

/**
 * ...
 * @param NVLL_IMAP $pop
 * @param array $subscribed
 * @return string
 */
function set_list_of_folders($pop, $subscribed)
{
    if (isset($_REQUEST['sort']) && isset($_SESSION['list_of_folders'])) return $_SESSION['list_of_folders'];

    $new_folders = array();
    $list_of_folders = '';

    foreach ($subscribed as $folder) {
        $folder_name = substr(strstr($folder->name, '}'), 1);
        $status = $pop->status($folder->name);
        $unseen = 0;

        if (isset($status['unseen']) && $status['unseen'] > 0) $unseen = $status['unseen'];
        if ($unseen > 0) {
            if (!in_array($folder_name, $new_folders)) {
                $list_of_folders .= ' <a href="api.php?' . NVLL_Session::getUrlGetSession() . '&folder=' . $folder_name . '">' . $folder_name . " ($unseen)" . '</a>';
                $_SESSION['list_of_folders'] = $list_of_folders;
                array_push($new_folders, $folder_name);
            }
        }
    }

    return $list_of_folders;
}
