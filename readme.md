# Email Newsletters

- Version: 1.0RC3
- Date: 2010-06-29
- Requirements: Symphony CMS 2.0.7 or newer, <http://github.com/symphony/symphony-2>
- Author: Michael Eichelsdoerfer
- GitHub Repository: <http://github.com/michael-e/email_newsletters>
- Available languages: English (default), German


## Synopsis

The Email Newsletters extension for the Symphony XSLT CMS has been developed because in the past I have been really unhappy with standalone software solutions. In nearly every respect (footprint, maintainability, email code control and author workflow) an on-board solution for Symphony seems the way to go. The Email Newsletters extension allows for sending email newsletters directly from Symphony's backend.

This extension is powerful. But in order to not sacrifice flexibility, some design decisions have been made which make things hard wrap your mind around. So:

1. You should already have some experience in using the Symphony XSLT CMS.
2. Please read and follow the documentation thoroughly before posting any bug reports or feature requests.

### The big picture: How this extension works

The Email Newsletters extension provides a news field type called _Email Newsletter_. This field has lots of configuration options like SMTP connection, newsletter content and recipient pages. Any content needed to send emails (HTML, pure text and recipient information) will be loaded using pages of your website, i.e. Symphony pages. Once you have set up those pages and managed the field's configuration (which is explained in detail below), you have won.

If you add the field to a section, you will find the field's publish panel on the entry edit page, featuring sender selection (if more than one sender is configured), checkboxes for recipient groups (if more than one recipient group is configured), content preview links and the send button. Upon sending, the panel will be reloaded every few seconds and it will show the processing status.

Sending an email newsletter invokes background processes (using the PHP CLI SAPI - see below). The background mailer engine will load the following using cURL:

- a newsletter HTML page (containing the newsletter's HTML content)
- a newsletter TEXT page (containing the newsletter's TEXT content)
- a recipients XML page (containing any recipient data)

If you are using the throttling feature (which is highly recommended), Emails will be sent in "slices", each one being an own PHP process.

Of course the XML page containing your recipients' data should be protected (details below).

### Features

- background processes for sending
- feedback in the publish panel (and in the entry overview table)
- send html and/or text emails
- multiple recipient groups
- flexible recipient personalization
- multiple senders
- "sender personalization" (using the field's datasource output)
- verbose log files, gzipped (if available)

### What this extension won't do

At the time of writing the following features are not supported nor planned:

- email campaign statistics/tracking
- email bounce management


## Legal

This Symphony extension is released under the MIT/X11 license. The license file is included in the distribution.

This extension also includes a copy of the Swift Mailer library, which is released under the GNU Lesser General Public License.

Please be aware of morality and legal conditions in your country concerning mass mailings. In many countries special recipient opt-in and opt-out procedures may be required, and you might encounter the need to store opt-in evidence on your server. Meeting such regulations is beyond the scope of this extension.

Never use this extension for SPAM. If you do so I will hate you.


## Installation & Updating

Information about [installing and updating extensions](http://symphony-cms.com/learn/tasks/view/install-an-extension/) can be found in the Symphony documentation at <http://symphony-cms.com/learn/>.

**There have been changes to the field's database structure which are not covered by the updater function.** So if you have already set up RC1, plesase uninstall the field and the extension, the install the latest version. (Make sure to have a copy of your XML configuration which can be adopted to the new XML structure.)


## Prerequisites

In order to successfully send email newsletters, you will need the following:

- An SMTP Email Box (access using a user/password combination; "SMTP after POP" access is not supported)

- PHP 5.2.+ (like Symphony 2)

- `safe_mode off`; Safe Mode apparently was no good idea. It is deprecated in PHP 5.3.0 and is removed in PHP 6 (<http://php.net/features.safe-mode>).

- PHP cURL support enabled (since we are not using Symphony's gateway class)

- PHP CLI (Command Line Interface) SAPI (Server Application Programming Interface)

	In simple words, the CLI SAPI allows to run PHP scripts from the command line, and **this can be initiated from within PHP scripts** even on hosting accounts without shell access. The Email Newsletters extension runs the actual (background) mailing processes using the PHP CLI SAPI. Some useful articles on this topic:

	- <http://articles.sitepoint.com/article/php-command-line-1>
	- <http://articles.sitepoint.com/article/php-command-line-2>
	- <http://php.net/manual/en/features.commandline.php>

	If you are unsure if the PHP CLI SAPI is installed and you have command line access, type

		php -v

	in your shell. If you don't get a verbose answer, the CLI SAPI is not installed. On Debian, you may install it by typing

		apt-get install php5-cli

	If you are on a shared hosting account, you should ask your provider. (The CLI should be installed on most shared hosting accounts.)

- Some sort of recipient management (e.g. Members extension); please respect the legal situation in your country!

- Content Type Mappings extension; this extension will allow you to define a TEXT page type, which is very handy when you try to preview your email's text content or your recipients XML page in the browser. After installing the extension, you may add `text type: 'text' => 'text/plain; charset=utf-8',` to the extension's settings in `config.php`.


## Setting up pages

This chapter describes the setup process for:

- the newsletter HTML page
- the newsletter TEXT page
- the recipients page

In order to send Newsletters, you should have created a TEXT page and - optionally - an HTML page to display entries of this section as email content. These pages' URLs will have a dynamic portion used to specify the entry. Use {$param} syntax, e.g. {$id} or {$title}:

- {$title} or similar: any field in the section (use the field handle)
- {$id}: the ID of the entry

Any pages are described using the page ID (which you will see in the page's "edit" URL) plus a *url-appendix* which may contain the above parameter syntax.

HTTP redirects are followed. If '200 OK' is not found in the HTTP response headers, an error will be thrown and the sending process will be aborted.

### Newsletter HTML page

Please don't underestimate the amount of work to create "stylish" email newsletters using HTML. You will find lots of resources on the web on this topic, so let's just name the basics here:

- use **layout tables**; don't use CSS positioning; (this is no joke);
- use **inline styles** exclusively;
- beware of image blocking in email clients; see <http://www.campaignmonitor.com/resources/entry/677/image-blocking-in-email-clients/>, for example.

### Newsletter TEXT page

A Symphony page which contains the newsletter's TEXT content (based on your section data). During development is has turned out that website authors may be used to writing using Markdown or even HTML markup. This is why you should not use unformatted textarea content to create email text content. Instead you may use XSLT to build the text content (based on your datasource's formatted, i.e. HTML, output).

An XSLT file suited for this purpose has been [released separately](http://symphony-cms.com/download/xslt-utilities/view/46794/ "Download – XSLT Utilities – “HTML to Email Text” – Symphony."). ([Discussion](http://symphony-cms.com/discuss/thread/46795/ "Discuss – Forum Thread – “HTML to Email Text” – Symphony."))


### Recipients Page(s)

Any recipients page must be an XML page. While this adds an additional level of complexity for unexperienced users, this is still desirable because of the level of flexibilty it provides. In an XML page, you may combine several dynamic and static datasources, and you may control the output using XSLT. Thus you can use all of Symphony's flexibility to create your group of recipients (Recipients XML page).

A very simple (Symphony) XML page XSL might look like this:

	<?xml version="1.0" encoding="UTF-8"?>
	<xsl:stylesheet version="1.0"
		xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

	<xsl:output method="xml"
		omit-xml-declaration="yes"
		encoding="UTF-8"
		indent="yes" />

	<xsl:template match="/">
		<xsl:copy-of select="."/>
	</xsl:template>

	</xsl:stylesheet>

And the output might be:

	<data>
		<events/>
		<recipients>
			<section id="1" handle="recipients">Recipients</section>
			<entry id="3">
				<email handle="dummy-domain1com">dummy@domain1.com</email>
				<name handle="john-doe">John Doe</name>
			</entry>
			<entry id="2">
				<email handle="dummy-domain2com">dummy@domain2.com</email>
				<name handle="jane-doe">Jane Doe</name>
			</entry>
			<entry id="1">
				<email handle="dummy-domain3com">dummy@domain3.com</email>
				<name handle="robinson-crusoe">Robinson Crusoe</name>
			</entry>
		</recipients>
	</data>

The important thing to note is: For each newsletter channel you will have to configure the `entry` nodes as well as the appendant `email` and `name` nodes. In the above case all these nodes are named "as expected" - but this is not necessary.

If you use more than one datasource, the output might look like this:

	<data>
		<events/>
		<recipients>
			<section id="1" handle="recipients">Recipients</section>
			<entry id="1">
				<email handle="dummy-domain1com">dummy@domain1.com</email>
				<name handle="john-doe">John Doe</name>
			</entry>
		</recipients>
		<static-xml>
			<entry id="2">
				<email handle="dummy-domain2com">dummy@domain2.com</email>
				<name handle="jane-doe">Jane Doe</name>
			</entry>
			<entry id="1">
				<email handle="dummy-domain3com">dummy@domain3.com</email>
				<name handle="robinson-crusoe">Robinson Crusoe</name>
			</entry>
		</static-xml>
	</data>

This XML page will still work with the Email Newsletters extension, as long as the important nodes (entry/email/name) are named according to your channel preferences. You can achieve this with some XSLT magic.


## Configuration

### Global confguration (Preferences)

SwiftMailer Location: You may change the location of the SwiftMailer library. I am rather sure that over time there will be more extensions using SwiftMailer. By making the library's location configurable it will be possible to use only one (central) Swiftmailer library instead of one per extension. At the moment, of course, you may leave this field empty if you don't move the library which is included in the download.

### Field Configuration (XML, Section Editor)

All of the field configuration is done using XML.

In the beginning the Email Newsletters extension provided most of the configuration options using Symphony'y native text input fields, select boxes etc. Any developer is used to this kind of configuration in the section editor. As the extension evolved, however, configuration options increased heavily, and building a useful interface took more and more time. The extension code was bloated just to create a GUI for configuration. So I decided to force all configuration into a single XML text, editable from within a simple textarea. I will not revert this decision, since it it not useful to put a siginficant amount of development time in building a graphical interface for this kind of configuration. Any Symphony developer knows how XML works.

XML configuration error handling may or may not be added in a future version. At the moment you must simply do it right. :-)

I have not written a DTD for this XML. But I am providing in-depth explanations below.

This is the minimum configuration:

	<email-newsletter>
		<live-mode>1</live-mode>
		<subject-field-label>Title</subject-field-label>
		<senders>
			<item id="1" email="ted@example.com" smtp-host="smtp.example.com" smtp-port="" smtp-username="ted" smtp-password="tedspassword" reply-to="management@example.com">Ted Tester</item>
		</senders>
		<recipients>
			<group id="1" page-id="141" entry-node="entry" name-node="name" email-node="email" code-node="code">Deutschland</group>
		</recipients>
		<content>
			<page-html page-id="151" url-appendix="{$id}/"/>
			<page-text page-id="152" url-appendix="{$id}/"/>
		</content>
	</email-newsletter>

And here is a full-blown example:

	<email-newsletter>
		<live-mode>0</live-mode>
		<debug-info>1</debug-info>
		<subject-field-label>Title</subject-field-label>
		<senders>
			<item id="1" smtp-host="smtp.example.com" smtp-port="" smtp-username="newsletter@example.com" smtp-password="mysecretpassword" from-email="ted@example.com" from-name="Teddy" reply-to-email="management@example.com" reply-to-name="Teddy2" return-path="returningmails@example.com">Ted Tester</item>
			<item id="2" smtp-host="smtp.example.com" smtp-port="" smtp-username="we3mast3r" smtp-password="password!" from-email="webmaster@example.com" from-name="" reply-to-email="webby@example.com" reply-to-name="" return-path="">Example Webmaster</item>
		</senders>
		<recipients>
			<group id="1" page-id="141" url-appendix="" entry-node="entry" name-node="name" email-node="email" code-node="code">Clients</group>
			<group id="2" page-id="142" url-appendix="?region=usa" entry-node="partner" name-node="full-name" email-node="email" code-node="secret-code">Partners (USA)</group>
			<group id="3" page-id="142" url-appendix="?region=bavaria" entry-node="partner" name-node="full-name" email-node="email" code-node="secret-code">Partners (Bavaria)</group>
		</recipients>
		<content>
			<page-html page-id="151" url-appendix="{$id}/"/>
			<page-text page-id="152" url-appendix="{$id}/"/>
		</content>
		<search-strings>
			<item replacement-node="email-node">[[email]]</item>
			<item replacement-node="name-node">[[name]]</item>
			<item replacement-node="code-node">[[code]]</item>
		</search-strings>
		<throttling>
			<emails-per-time-period>50</emails-per-time-period>
			<time-period-in-seconds>30</time-period-in-seconds>
		</throttling>
	</email-newsletter>

Please note that any id attributes must have unique values (in their context).

Meaning of those XML elements (and/or attributes):

- Live Mode (optional)

	If not explicitely set to '1', the following will happen:

	- Information about this status will be appended to the publish panel.
	- No emails will be sent. The first line in the log will reflect this.
	- There will be "retry" button upon successful sending, restting the newsletter data in the DB (e.g. the status). This eases developers' work - sending of a newsletter may be re-started without fiddling around in the DB.

- Debug Info (optional)

	If explicitely set to '1', debug information will be shown in the publish panel. Debug information contains:

	- Logged-in developers will see a working preview link to recipients (XML) pages if the page path can be resolved (i.e. a page ID is found in the XML and the page path is found is Symphony's database). This information is rather useless for non-developers, so it is omitted for authors. If there is only one recipient group, it will **not** be hidden (like it is the case for authors or w/o debug mode).

- Subject Field Label (required)

	This field's value will be used for the email subject. You will probably choose a text input field (like the "Title" field of your section).

- Senders (required)

	- item (required: one; optional: multiple)

		Most of the sender attributes should be self-explanatory.

		While the `return-path` option may be useful from time to time, you should be careful with this - if the return path's domain does not match your SPF record's domain, your SPF record won't do any good to your SPAM score...

		If multiple senders are configured, a sender dropdown menu will be shown.

- Recipients (required)

	- group (required: one; optional: multiple)

		Groups are described using recipients XML pages. If multiple groups are configured, checkboxes will be shown  which allow to choose one ore more recipient groups to send to. (Please note that recipients will not receive more than one email even if they belong to more than one of the selected groups. See below: "Recipient email duplicates".)

		- ID

			If the group ID is missing, the group can not be saved.

		- page-id

		- url-appendix

			You may provide an additional string here (which may contain param syntax which is probably never needed) to be appended to the URL. This is very useful to handle several recipient groups with just one Symphony XML page using datasource filtering.

		- entry node
		- name node (must be a child node of the entry node)
		- email node (must be a child node of the entry node)
		- additional nodes

			Additional nodes may be be used for "search and replace"" operations (e.g. to build personalized opt-out links in emails)

- Content (required; must hav at least one child)

	- HTML page (optional)

		Symphony page which contains the newsletter's HTML content (based on your entry data). See above.

		- page id

		- url-appendix

			You may provide an additional string here (which may contain param syntax) to be appended to the URL. This allows datasource filtering using IDs or entry titles.

	- TEXT page (optional)

		Symphony page which contains the newsletter's TEXT content (based on your entry data). See above.

		- page id

		- url-appendix

			You may provide an additional string here (which may contain param syntax) to be appended to the URL. This allows datasource filtering using IDs or entry titles.

- Search Strings (optional)

	- Item (optional, one or multiple)

- Throttling (optional)

	If throttling is enabled (i.e. configured), the publish panel will show the "estimated time left" as soon as the first bunch of emails has been sent. This can be very useful for big recipient groups. Please note that the estimation is based on throttling setting, NOT on the actual speed of your email server. (Ideally the throttling speed should be below the actual speed, of course. You may ask your provider about the best values here. Might be s.th. like "50 emails every 30 seconds".)


## Data Source output

The data source output of the Email Newsletter field contains:

- author-id
- status
- total (emails)
- sent (emails)
- errors (emails)
- sender
- recipient groups

It will look like this in your page XML:

	<email-newsletter author-id="1" status="processing" total="602" sent="120" errors="0">
		<sender id="1">Michael E.</sender>
		<recipients>
			<group id="1">Clients</group>
			<group id="3">Partners (Bavaria)</group>
		</recipients>
	</email-newsletter>


## Param Pool value

If you use the Email Newsletter field to be output to the param pool (for Data Source chaining), output will be the **sender ID**! (This seems to be the most useful output.)


## The "Send" button

The "Send" button actually is a "Save and Send" button, so it will save the entry and start the "mailing engine" with a single click. I think that this is what people expect this button to do. (The implementation in Symphony has been rather hard.)

If you click the button, the system will prepare for sendind (e.g. count the recipients and display the number in the GUI), then wait for some seconds before actually starting the send process. This allows for "last minute cancelling" in case a user has not really (?) meant to really (!) send the newsletter. :-)


## Before you start

Please note that successfully sending mass mailings will require your email box to be set up "more than correctly". So please check the following:

- correct MX records
- SPF (Sender Policy Framework) record
- optional: reverse DNS entry (PTR/Reverse DNS checks)
- optional: Domain Keys / DKIM

It is beyond the scope of this software to explain these measures in detail. Anyway the first two are really important if you don't want your email to be flagged as spam. If you don't know what it is, ask your provider or consult the web (i.e. Google, isn't it?).

Here are some useful links concerning SPF records:

- <http://phpmailer.codeworxtech.com/index.php?pg=tip_spf>
- <http://old.openspf.org/wizard.html>

Here is a simple example DNS record which worked very well in my tests:

	example.com. IN TXT "v=spf1 a mx"


## Logging

By design the extension will log every Newsletter that has been sent, including the following details:

- newsletter subject
- newsletter HTML content
- newsletter TEXT content
- recipients (array format)
- recipient "slices" (array format)
- statistics

Logs are saved in `/manifest/logs/email-newsletters`. If the `gzencode` PHP function is available. logs will be saved as compressed `.tgz` files. They may be useful for "historical" oder legal reasons. If you are missing any useful information in these logs, please drop me a line.


## Security Concerns

### Protecting the Recipients Page (and Content Pages alike)

You may consider the following measures in order to secure your recipients data:

- page URL: may contain a "password portion" (which may be changed at any time, because the page is referenced by its ID in the XML configuration);
- page XSLT: compare remote IP to server IP using the Server Headers extension; don't output any useful data if the condition is not met;
- page type 'admin'

If you are using the latter (which is recommended), the extension will automatically remove the 'admin' page type before loading the page and add the 'admin' page type again immediately after loading. This will happen very fast, so it is not considered a security flaw.

All these measures may be taken for content pages as well, in case you don't want to have the email's content accessible on the web. If you have a newsletter archive on your website, you may even consider the following:

- content pages for newsletters which are "published" on the web - datasource checks for a "published" flag
- separate content pages for use with the extension (preview plus loading!) - datasource does not check the "published" flag, but the page has the page type 'admin'

### Protecting the Logfiles

By default your Symphony logfiles (and the Email Newsletters logfiles alike) will be readable from the web. In order to protect your logfiles you should consider appropriate techniques. On Apache you may simply place an `.htaccess` file in `manifest/logs/email-newsletters` (or in `manifest/logs`) with the following content:

	Order deny,allow
	Deny from all


## Miscellaneous

### Internationalization

I am providing this extension with a German language file. More translations are welcome, but it should be noted that language strings might change massively for the "official" 1.0 release.

### Recipient email duplicates

By design the extension will not send an email to one address multiple times. This is due to the design of the SwiftMailer library. As written in the documentation ([Adding Recipients to Your Message](http://swiftmailer.org/docs/recipients "Adding Recipients to Your Message – Swift Mailer")), any recipient list must be an array using the recipient's email address as key. So if an email address is included multiple times in your recipients XML page, the last address/name pair will be used.

### Ampersands

You will probably find no way to display ampersands as `&` on your TEXT preview page. This is by nature of XSLT: ampersand will always be encoded as `&amp;`. However, upon sending those entities will be replaced by `&`, and the Swiftmailer library will leave them untouched. Since the newsletter TEXT page will probably be used for preview purposes only, this is regarded a minor flaw. Maybe it will be addressed in future versions.


## Known issues

1. There are bugs concerning HTML form button values in Internet Explorer 6 and 7 (which shouldn't be used for Symphony anyway). This means that:

	- You won't be able to send a newsletter in IE6 (who cares?)
	- You won't be able to handle multiple Email Newsletters (i.e. Email Newsletter fields) **in the same section** using IE7. This is considered a rare setup (but is actually a supported feature in modern browsers).

	These constraints are regarded a small price for having a combined "Save and Send" button (which is simply called "Send"). (We actually need the button's value to implement this functionality.)

## Change Log

### 1.0RC2

Release-date: 2010-06-30

Bugfixes and overall improvements.

### 1.0RC2

Release-date: 2010-06-28

- Changed: implementation of loading pages with page type 'admin'
- Changed: XMl configuration elements/attributes for recipients and content pages

### 1.0RC1

Release date: 2010-06-27

This is the initial release.
