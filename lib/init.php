<?php

	/**
	 * Mailer initialisation
	 * to be used with the PHP Command Line Interface (CLI) SAPI
	 *
	 * @package Email Newsletters
	 * @author Michael Eichelsdoerfer
	 */
	require_once('class.email_newsletter.php');

	$field_id = $argv[1];
	$entry_id = $argv[2];
	$domain   = $argv[3];
	$mode     = $argv[4];

	$emailnewsletter = new EmailNewsletter($field_id, $entry_id, $domain);

	if     ($mode == 'init')   $emailnewsletter->init();
	elseif ($mode == 'resume') $emailnewsletter->send();

	exit(0);
