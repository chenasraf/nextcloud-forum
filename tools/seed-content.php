<?php

// SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
// SPDX-License-Identifier: AGPL-3.0-or-later

// Content for tools/seed.php: the accounts that post and the threads they write.
// Appended after bootstrap.php, so it has no opening declarations of its own.

/**
 * @return list<array{id: string, name: string}> The seed accounts; IDs get FORUM_SEED_USER_PREFIX
 */
function seed_people(): array {
	return [
		['id' => 'maya.levi', 'name' => 'Maya Levi'],
		['id' => 'daniel.okafor', 'name' => 'Daniel Okafor'],
		['id' => 'sofia.rossi', 'name' => 'Sofia Rossi'],
		['id' => 'kenji.watanabe', 'name' => 'Kenji Watanabe'],
		['id' => 'amira.haddad', 'name' => 'Amira Haddad'],
		['id' => 'lukas.berger', 'name' => 'Lukas Berger'],
		['id' => 'priya.nair', 'name' => 'Priya Nair'],
		['id' => 'tom.hughes', 'name' => 'Tom Hughes'],
		['id' => 'ines.carvalho', 'name' => 'Inês Carvalho'],
		['id' => 'oleg.petrov', 'name' => 'Oleg Petrov'],
		['id' => 'grace.kim', 'name' => 'Grace Kim'],
		['id' => 'mateo.fernandez', 'name' => 'Mateo Fernández'],
		['id' => 'hannah.schmidt', 'name' => 'Hannah Schmidt'],
		['id' => 'yusuf.demir', 'name' => 'Yusuf Demir'],
		['id' => 'chloe.martin', 'name' => 'Chloé Martin'],
		['id' => 'sam.taylor', 'name' => 'Sam Taylor'],
	];
}

/**
 * Pick the topic theme that fits a category, from its name, slug and description
 */
function seed_theme_for_category(string $text): string {
	$text = strtolower($text);
	$themes = [
		'support' => ['support', 'help', 'question', 'issue', 'problem', 'bug', 'troubleshoot', 'faq'],
		'feedback' => ['feedback', 'idea', 'feature', 'suggest', 'request', 'proposal', 'roadmap'],
		'offtopic' => ['off-topic', 'off topic', 'offtopic', 'random', 'lounge', 'social', 'chat', 'fun', 'hobb'],
		'announcements' => ['announce', 'news', 'update', 'release', 'event'],
	];
	foreach ($themes as $theme => $keywords) {
		foreach ($keywords as $keyword) {
			if (str_contains($text, $keyword)) {
				return $theme;
			}
		}
	}
	return 'general';
}

/**
 * Filler replies for a theme, in two kinds: `standalone` replies to the thread as a whole, and `answer`
 * replies to one earlier reply, whose author's first name replaces {name}
 *
 * @return array{standalone: list<string>, answer: list<string>}
 */
function seed_filler_replies(string $theme): array {
	$standalone = [
		'Good thread. Saving it for later.',
		'Following this one.',
	];
	$answer = [
		'Agree with {name} here.',
		'Good point, {name}.',
	];

	if ($theme === 'support') {
		$standalone = array_merge($standalone, [
			'Same problem here, following.',
			'Thanks for writing this up so clearly, I ran into something similar.',
		]);
		$answer = array_merge($answer, [
			'Thanks {name}, that fixed it for me too.',
			'{name}, do you have a link to the documentation for that?',
			'Quick question {name}: does that also apply to the mobile app?',
			'Can confirm what {name} said, same result here.',
			'{name} beat me to it, I was about to suggest the same thing.',
			'Not sure that is it, {name}. I tried that and it did not change anything for me.',
		]);
	} elseif ($theme === 'feedback') {
		$standalone = array_merge($standalone, [
			'+1 from me.',
			'Would love this.',
			'Is there an issue tracker entry for this I can vote on?',
			'Not everyone would use it, but as an option it sounds great.',
		]);
		$answer = array_merge($answer, [
			'+1 to what {name} said.',
			'{name}, that would cover my use case as well.',
			'I see it differently, {name}. I would rather keep it simple.',
			'{name} makes a good point, that should be part of the request.',
		]);
	} else {
		$standalone = array_merge($standalone, [
			'Love threads like this.',
			'Interesting to see how differently everyone does it.',
			'Taking notes, lots of good ideas here.',
		]);
		$answer = array_merge($answer, [
			'Thanks {name}, I will try that.',
			'Same here, {name}!',
			'{name}, how long have you been doing it that way?',
			'I had not thought of that, {name}. Nice one.',
			'Ha, I can relate to that, {name}.',
		]);
	}

	return ['standalone' => $standalone, 'answer' => $answer];
}

/**
 * Filler replies that only make sense once a thread has been going for a while
 *
 * @return list<string>
 */
function seed_late_replies(): array {
	return [
		'Late to the party, but thanks everyone for the input.',
		'This deserves to be pinned somewhere.',
		'Great thread, learned a lot reading through it.',
	];
}

/**
 * Short closing lines from the person who started the thread
 *
 * @return list<string>
 */
function seed_op_closers(): array {
	return [
		'Thanks everyone, this gave me plenty to work with.',
		'Appreciate all the replies!',
		'Great discussion, thanks all.',
	];
}

/**
 * Thread topics. `replies` read in order as the conversation goes on. `op` are follow-ups from the person
 * who started the thread, each as [index of the reply it answers, text]; it is posted shortly after that
 * reply.
 *
 * @return list<array{theme: string, title: string, body: string, replies: list<string>, op: list<array{int, string}>}>
 */
function seed_topics(): array {
	return [
		// Support
		[
			'theme' => 'support',
			'title' => 'Desktop client keeps asking me to log in again',
			'body' => "Since last week the desktop client on my laptop asks me to log in every morning. It syncs fine after that, but it is getting annoying.\n\nI am on Windows 11 with the latest client. Anyone else seeing this?",
			'replies' => [
				'Do you have two-factor authentication enabled? The client sometimes loses the app password after an update.',
				'I had exactly this. Removing the account from the client and adding it again fixed it for me.',
				'Check whether your laptop clears the credential store on shutdown. Some company policies do that.',
				'You could also create an app password under Settings > Security and use that in the client instead.',
				'It happened to me after the server was moved to a new domain. The old login flow was still cached.',
				'If it keeps happening, the client log usually says why. It is under the settings, "Create debug archive".',
				'Same issue here on macOS, so it is probably not Windows specific.',
				'An app password solved it for my whole team, for what it is worth.',
			],
			'op' => [
				[0, 'Yes, I do have 2FA on. Could that be it?'],
				[1, 'Re-adding the account worked! Two mornings in a row without a login prompt now.'],
				[3, 'Created an app password as well, just to be safe.'],
			],
		],
		[
			'theme' => 'support',
			'title' => 'Upload fails for files larger than 1 GB',
			'body' => "Uploading anything over roughly 1 GB through the browser stops at about 95% and then shows a generic error. Smaller files upload without problems.\n\nIs there a size limit I am missing?",
			'replies' => [
				'Sounds like a timeout on the proxy rather than a size limit. Do you know if there is a reverse proxy in front of the server?',
				'The browser upload uses chunks, so the file size itself should not matter. The final assembly step can time out though.',
				'Ask the administration team to check the PHP max_execution_time and the proxy read timeout.',
				'For really big files the desktop client is more reliable anyway.',
				'We had this and it was the [icode]client_max_body_size[/icode] setting in nginx.',
				'Is the error the same in every browser? Firefox gave me a more detailed message.',
				'You can also try putting the file in a folder that is synced and let the client handle it.',
				'Did the administration team get back to you? Curious what the fix was.',
			],
			'op' => [
				[0, 'There is an nginx proxy, yes. I will forward this to our administration team.'],
				[5, 'Firefox says "Gateway Timeout", so the proxy theory sounds right.'],
				[7, 'They raised the proxy timeout and the 3 GB video went through on the first try. Thanks all!'],
			],
		],
		[
			'theme' => 'support',
			'title' => 'Calendar invites show the wrong time zone',
			'body' => 'When I send a meeting invite from the calendar, people outside the company receive it an hour off. Inside the company it looks right. What am I doing wrong?',
			'replies' => [
				'Check the time zone in your personal settings. It defaults to the browser time zone, which can be wrong.',
				'Is this around a daylight saving change? Some mail clients handle that badly.',
				'Which mail client do the external people use? Outlook is known to be picky about time zone definitions.',
				'I had this when my calendar was set to UTC but my profile was set to Europe/Berlin.',
				'Try creating the event again after fixing the time zone, existing events keep their old one.',
				'Our external partners use Gmail and it works fine for them, so it might be a client thing.',
				'You can see the time zone of an event when you open its details, worth double checking.',
			],
			'op' => [
				[2, 'The external people are mostly on Outlook, yes.'],
				[3, 'Found it: my calendar was on UTC. Changed it and the next invite arrived with the right time.'],
			],
		],
		[
			'theme' => 'support',
			'title' => 'Shared folder disappeared from my files',
			'body' => 'A colleague shared a project folder with me a month ago. Today it is just gone from my files. She says she did not remove the share. Where could it have gone?',
			'replies' => [
				'Check the "Shared with you" section on the left. If it is there, it may have been moved inside another folder.',
				'Could the share have expired? Shares can get an expiration date, sometimes set by default.',
				'Look in the deleted files too. If you removed it by accident, it shows up there as a share you left.',
				'Did she maybe change the share from her account to a group you are not in?',
				'The activity view sometimes says when a share was removed and by whom.',
				'Expiring shares bit us more than once. The default expiry was set to 30 days by the administration.',
				'If it expired, she can just share it again and everything is back, nothing is lost.',
			],
			'op' => [
				[0, 'It is not in "Shared with you" either.'],
				[4, 'The activity view says "share expired". That explains it!'],
				[6, 'She shared it again without an expiry date and it is back. Thanks!'],
			],
		],
		[
			'theme' => 'support',
			'title' => 'How do I restore an older version of a document?',
			'body' => 'I overwrote a spreadsheet with the wrong data and saved it. Is there any way to get yesterday\'s version back?',
			'replies' => [
				'Yes! Open the file details in the sidebar and look for the "Versions" tab.',
				'Versions are kept automatically, so you should see a list with timestamps.',
				'Click the restore icon next to the version you want and it becomes the current one again.',
				'The current version is not lost when you restore, it becomes a version itself. So it is safe to try.',
				'Saved my week more than once, this feature.',
				'You can also give versions a name, handy for marking the "good" one.',
				'Note that old versions are cleaned up over time if storage runs low, so do not wait too long.',
			],
			'op' => [
				[0, 'Found the Versions tab, I had never noticed it before.'],
				[3, 'Restored it, all the data is back. You are lifesavers.'],
			],
		],
		[
			'theme' => 'support',
			'title' => 'Mobile app drains the battery',
			'body' => 'Since enabling auto upload for photos on my phone, the battery is empty by mid-afternoon. Is that expected?',
			'replies' => [
				'The first auto upload can take a long time if you have many photos. It should calm down once it has caught up.',
				'Set it to upload only on Wi-Fi and only while charging, that is what I do.',
				'Which phone are you using? Some Android vendors are very aggressive about background apps.',
				'If you have thousands of photos, it might be worth doing the first upload from a computer.',
				'Check that you did not enable it for the whole internal storage by accident.',
				'Mine was uploading every WhatsApp image too. Excluding that folder helped a lot.',
				'After the initial sync my battery usage went back to normal.',
			],
			'op' => [
				[0, 'I have about 12,000 photos, so that explains a lot.'],
				[1, 'Switched to "only while charging" and left it overnight. Most of it is done now.'],
				[6, 'Battery is back to normal today. Thanks for the tips!'],
			],
		],
		[
			'theme' => 'support',
			'title' => 'Search does not find text inside PDFs',
			'body' => 'When I search for a word I know is in one of our PDF manuals, nothing comes up. File names are found fine. Is full-text search supported?',
			'replies' => [
				'Full-text search needs an extra app and a search backend. Out of the box only file names are indexed.',
				'Ask the administration team whether full-text search is installed. It needs Elasticsearch or OpenSearch.',
				'Scanned PDFs need OCR on top of that, otherwise there is no text to index at all.',
				'We have it running and it works well, but indexing the first time took a whole weekend.',
				'In the meantime you can add tags to the manuals so they are easier to find.',
				'Unified search does show file contents if the indexer is set up, so the interface is ready for it.',
				'The PDF viewer has its own search once the file is open, which helps for single documents.',
			],
			'op' => [
				[0, 'That explains it, I assumed it was built in.'],
				[4, 'I will raise it with the administration team. Tags will do for now.'],
			],
		],
		[
			'theme' => 'support',
			'title' => 'Cannot open .docx files in the browser',
			'body' => 'Clicking a Word document just downloads it instead of opening it in the browser. My colleague can open the same file online. Is this a permission thing?',
			'replies' => [
				'Does your colleague use the same browser? Some extensions block the office editor frame.',
				'Try a private window. If it opens there, an extension is the culprit.',
				'It could be a group restriction. The office app can be limited to certain groups.',
				'Check the file\'s menu. "Edit in office" sometimes appears even when clicking downloads.',
				'Our administration limited online editing to staff, interns could only download.',
				'Ad blockers were the problem for me. Whitelisting the domain fixed it.',
			],
			'op' => [
				[1, 'Private window opens it fine! So it is an extension.'],
				[5, 'It was the ad blocker. Whitelisted the domain and it works now, thanks.'],
			],
		],
		[
			'theme' => 'support',
			'title' => 'Two-factor authentication: lost my phone',
			'body' => 'My phone was stolen and it had my authenticator app on it. Now I cannot log in. What are my options?',
			'replies' => [
				'Did you save the backup codes when you set up two-factor authentication? Each one works once.',
				'If not, the administration team can disable two-factor for your account temporarily.',
				'Once you are back in, set up the authenticator on your new phone and generate new backup codes.',
				'Good moment to also check your devices and sessions list and revoke the old phone.',
				'Sorry to hear about the phone. Happened to me last year, the administration sorted it in ten minutes.',
				'Consider adding a second method, like a security key, so this cannot lock you out again.',
				'Also remember to revoke any app passwords the stolen phone had.',
			],
			'op' => [
				[0, 'No backup codes, unfortunately. Lesson learned.'],
				[3, 'The administration disabled it and I am back in. Revoked the old phone too.'],
				[6, 'Revoked its app password as well, and set up two methods this time with printed backup codes. Thanks everyone.'],
			],
		],
		[
			'theme' => 'support',
			'title' => 'Contacts not syncing to my phone',
			'body' => 'I added the CardDAV account on my Android phone, but no contacts appear. The calendar on the same account syncs fine. Any ideas?',
			'replies' => [
				'Which sync app are you using? DAVx5 is the most reliable one in my experience.',
				'In DAVx5 you have to tick each address book you want to sync, they are off by default.',
				'Check the contacts app too. It may be set to show only the phone\'s own contacts.',
				'If you have a lot of contacts the first sync can take a few minutes.',
				'Exactly this happened to me, the address book was not selected in DAVx5.',
				'Also make sure the phone contacts app has permission to show the DAVx5 account.',
			],
			'op' => [
				[0, 'I am using DAVx5, yes.'],
				[1, 'The address book was not ticked. All 340 contacts are on my phone now. Thanks!'],
			],
		],

		// General
		[
			'theme' => 'general',
			'title' => 'What does your folder structure look like?',
			'body' => 'Our shared drive has become a mess over the years. How do you organise folders in your team? Looking for ideas before we clean up.',
			'replies' => [
				'We go by year at the top, then by project. Old years get archived as a whole.',
				'Project first, year second for us. People search by project, not by date.',
				'Whatever you pick, write it down and pin it somewhere. Otherwise it drifts again in six months.',
				'We keep a "_templates" folder at the top so it sorts first.',
				'Tags help a lot when one file belongs in several places.',
				'Limit the depth. Anything deeper than four levels never gets found again.',
				'We use numbered prefixes like 01_Admin, 02_Projects so the order is stable.',
				'One owner per top-level folder helped us keep it tidy.',
			],
			'op' => [
				[6, 'The numbered prefixes idea is great, I will steal that.'],
				[7, 'We agreed on project first, then year, with one owner per top-level folder.'],
			],
		],
		[
			'theme' => 'general',
			'title' => 'Tips for running shorter meetings?',
			'body' => 'Our weekly team meeting regularly runs 90 minutes when it should take 30. What has worked for you to keep meetings short?',
			'replies' => [
				'Shared agenda in a document, and anything not on the agenda by the day before waits a week.',
				'We stand up for the weekly one. It is amazing how fast it goes.',
				'Status updates go in writing beforehand, the meeting is only for discussion.',
				'Timebox each agenda item and assign someone to keep time.',
				'End with a list of decisions and owners, then nobody needs a follow-up meeting.',
				'Honestly, half our meetings became a forum thread like this one.',
				'Cancel it when the agenda is empty. People love that.',
				'Written updates first changed everything for us.',
			],
			'op' => [
				[2, 'Written updates beforehand sounds like the biggest win. Trying it next week.'],
				[7, 'First week with written updates: 35 minutes. Thanks for the ideas!'],
			],
		],
		[
			'theme' => 'general',
			'title' => 'Which note taking setup do you use?',
			'body' => 'I keep switching between paper, the Notes app and plain text files. Curious what everyone else settled on and why.',
			'replies' => [
				'Plain Markdown files in a synced folder. They will open in any editor in twenty years.',
				'The Notes app for quick things, Collectives for anything the team needs to see.',
				'Paper for meetings, then I type up the actions afterwards. Writing helps me remember.',
				'I tried everything and came back to one big text file per year.',
				'Markdown plus the Text editor in the browser works well because I can edit from anywhere.',
				'Whatever syncs to the phone wins for me. Ideas come at the worst times.',
				'Collectives has been great for our team handbook.',
			],
			'op' => [
				[0, 'Plain Markdown in a synced folder sounds like the least lock-in.'],
				[6, 'Going with Markdown files, plus Collectives for team pages. Thanks!'],
			],
		],
		[
			'theme' => 'general',
			'title' => 'Introduce yourself!',
			'body' => "Lots of new faces around here lately, so let us get to know each other. Who are you, what do you work on, and what is one thing you want to learn this year?\n\nI will start: I work in the finance team and I want to finally learn proper spreadsheet formulas.",
			'replies' => [
				'Hi all! Design team here. This year I want to learn some basic front-end coding.',
				'Hello! I look after our office network and printers. Goal for this year: run a half marathon.',
				'Hi, I joined the marketing team two weeks ago. I want to get better at public speaking.',
				'Long-time lurker from operations. I would like to learn to cook something other than pasta.',
				'Developer here. I want to contribute to an open source project this year.',
				'Hey! Customer support. Learning Spanish, so feel free to say hola.',
				'HR team. My goal is to read 20 books this year, recommendations welcome.',
				'Facilities manager. I want to learn to play the guitar I bought three years ago.',
			],
			'op' => [
				[5, 'Love this! Hola to our support colleague.'],
				[6, 'For the reading goal, "The Mom Test" is short and very good.'],
			],
		],
		[
			'theme' => 'general',
			'title' => 'Best practices for naming files?',
			'body' => 'We keep ending up with "final_v2_really_final.docx". How do you name files so people can tell versions and dates apart?',
			'replies' => [
				'Date first in ISO format: 2026-03-14_Report.docx. Sorts correctly everywhere.',
				'Do not put versions in the name at all, the built-in versioning keeps the history.',
				'Avoid spaces and special characters if files get shared with external tools.',
				'We use project code, document type and date. Boring but it works.',
				'Mark the approved version with a tag instead of renaming it.',
				'A short naming guide in the folder itself helps new people a lot.',
				'ISO dates changed my life, not even joking.',
			],
			'op' => [
				[1, 'I did not know versioning made the v2/v3 thing unnecessary.'],
				[5, 'Writing a one-page naming guide now: ISO date, project, type. Thanks all.'],
			],
		],
		[
			'theme' => 'general',
			'title' => 'How do you handle working across time zones?',
			'body' => 'Half our team just moved to a different continent, seven hours apart. How do other distributed teams keep in touch without endless late calls?',
			'replies' => [
				'Pick a two-hour overlap window and protect it. Everything else is asynchronous.',
				'Record the important meetings so the other half can watch them in their morning.',
				'Write more. Decisions in a thread are visible to everyone regardless of time zone.',
				'Rotate the inconvenient meeting times so the same people do not always suffer.',
				'Show both times in invites. The calendar does this if the time zones are set correctly.',
				'Clear handover notes at the end of each day made a huge difference for us.',
				'We moved our standup to a written thread, works much better.',
			],
			'op' => [
				[3, 'Rotating the bad meeting slot is a fair idea, I will suggest it.'],
				[5, 'Daily handover notes plus a fixed overlap window. Week one went well!'],
			],
		],
		[
			'theme' => 'general',
			'title' => 'Share your desk setup',
			'body' => 'New office, new desk. Tell us what your work setup looks like and what you could not do without.',
			'replies' => [
				'A good chair. Everything else is secondary after a few years of back pain.',
				'Second monitor in portrait mode for reading long documents.',
				'Mechanical keyboard, sorry to my neighbours.',
				'A plant. Seriously, it makes the room nicer.',
				'Noise cancelling headphones, open office survival kit.',
				'Laptop stand plus external keyboard, my neck thanks me.',
				'A whiteboard next to the desk for thinking out loud.',
				'Big mug. Refills are the enemy of focus.',
			],
			'op' => [
				[1, 'Portrait monitor is something I have to try.'],
				[5, 'Ordered a laptop stand after reading this. Thanks for sharing, everyone.'],
			],
		],
		[
			'theme' => 'general',
			'title' => 'Keeping documentation up to date',
			'body' => 'Every team I have been in writes great docs once and then never touches them again. How do you make sure documentation stays current?',
			'replies' => [
				'Put an owner and a "last reviewed" date at the top of each page.',
				'Review docs as part of the change. No change is finished until the page is updated.',
				'Delete outdated docs aggressively. Wrong documentation is worse than none.',
				'We do a docs day every quarter. Pizza helps.',
				'Keep docs close to the work. If they live in another tool, nobody opens it.',
				'Short pages are easier to keep current than one giant handbook.',
				'A link to "report a problem with this page" gets surprisingly many fixes.',
			],
			'op' => [
				[0, 'Owner and last-reviewed date is easy to start with.'],
				[3, 'We are trying the quarterly docs day, pizza included. Thanks!'],
			],
		],
		[
			'theme' => 'general',
			'title' => 'Book club: what are you reading?',
			'body' => 'Starting a casual book thread. What are you reading right now, and would you recommend it?',
			'replies' => [
				'"Project Hail Mary". Could not put it down, highly recommended.',
				'Re-reading "Thinking, Fast and Slow". Dense but worth it.',
				'A cookbook, if that counts. "Salt Fat Acid Heat" taught me why recipes work.',
				'"The Ministry for the Future". Heavy topic, but very thought provoking.',
				'Just finished "Klara and the Sun". Quiet and beautiful.',
				'I mostly do audiobooks on the commute. Currently "Sapiens".',
				'"Project Hail Mary" is great, +1 to that.',
				'Anything by Terry Pratchett, always.',
			],
			'op' => [
				[6, 'Adding "Project Hail Mary" to my list, it keeps coming up.'],
				[7, 'What a list, my weekend is sorted. Thanks everyone!'],
			],
		],
		[
			'theme' => 'general',
			'title' => 'Onboarding checklist for new colleagues',
			'body' => 'We are hiring five people next month. What should be on an onboarding checklist so their first week is not chaos?',
			'replies' => [
				'Accounts ready on day one: login, email, chat, files. Nothing worse than waiting for access.',
				'A buddy who is not their manager, for the silly questions.',
				'A list of the five people they should meet in the first week.',
				'Give them a small real task early. Nothing builds confidence like shipping something.',
				'Write down where things live: shared folders, calendars, this forum.',
				'Schedule check-ins after week one and month one.',
				'A welcome thread here so people can say hi.',
			],
			'op' => [
				[1, 'The buddy idea is great, we never had that.'],
				[6, 'Checklist done and shared in our team folder. Thanks for all the input!'],
			],
		],

		// Feedback and ideas
		[
			'theme' => 'feedback',
			'title' => 'Idea: dark mode for the forum',
			'body' => 'The rest of the interface follows my dark theme, but some parts of the forum are still very bright. Would love proper dark mode support everywhere.',
			'replies' => [
				'Yes please. Reading long threads at night is painful.',
				'Which parts look bright for you? For me it is mostly the code blocks.',
				'The colored category cards are fine, but quotes have a white background.',
				'+1, especially for the post editor.',
				'It follows the system theme for me, maybe check your accessibility settings?',
				'Screenshots would help the developers a lot here.',
				'High contrast mode would be nice too, while we are at it.',
			],
			'op' => [
				[1, 'Mainly the quotes and code blocks, yes.'],
				[5, 'Attached a few screenshots to the issue tracker. Thanks for the support!'],
			],
		],
		[
			'theme' => 'feedback',
			'title' => 'Feature request: weekly digest email',
			'body' => 'I do not want an email for every reply, but I also miss interesting threads. A weekly digest with the most active threads would be perfect.',
			'replies' => [
				'Strong +1. Per-thread notifications are too noisy for me.',
				'Daily or weekly as an option would be ideal.',
				'Include unread counts per category in the digest and I am sold.',
				'Some people would want it only for subscribed categories.',
				'As long as it can be turned off, sounds good.',
				'Our old forum had this and it was the main way people found new discussions.',
				'Maybe the dashboard widget covers part of this already?',
			],
			'op' => [
				[3, 'Good point about subscribed categories only.'],
				[6, 'The dashboard widget helps, but email reaches people who never open the dashboard.'],
			],
		],
		[
			'theme' => 'feedback',
			'title' => 'Suggestion: let us pin threads per category',
			'body' => 'It would be useful to pin a "read this first" thread at the top of each category, not just globally.',
			'replies' => [
				'Pinning already works per category for moderators, I think?',
				'It does, the pin button is in the thread actions if you have the role.',
				'Maybe the request is for category owners who are not moderators?',
				'A "rules" field on the category itself could be an alternative.',
				'+1 for category-level moderators in general.',
				'We use pinned threads for FAQ and it works nicely.',
			],
			'op' => [
				[1, 'Ah, I did not have the role, so I never saw the button.'],
				[2, 'Yes, exactly. So the real request is category-level moderators.'],
			],
		],
		[
			'theme' => 'feedback',
			'title' => 'The post editor is great',
			'body' => 'Just wanted to say the post editor is really nice to use. Inserting code and lists is so easy. Thanks to whoever worked on it!',
			'replies' => [
				'Agreed, the preview is my favourite part.',
				'Keyboard shortcuts for bold and italic would make it perfect.',
				'Pasting images directly would be the next big step.',
				'It works well on mobile too.',
				'Thanks for posting positive feedback, it does not happen often enough.',
				'The templates feature is underrated as well.',
				'Yes, a pleasure to use.',
			],
			'op' => [
				[2, 'Pasting images would be great, agreed.'],
				[4, 'Nice to see so many people enjoying it.'],
			],
		],
		[
			'theme' => 'feedback',
			'title' => 'Proposal: reaction emojis on posts',
			'body' => 'Sometimes I just want to say "agreed" without writing a whole reply. Could we get more reactions, or custom ones?',
			'replies' => [
				'Reactions exist already, check the smiley icon on each post.',
				'Custom ones would be fun, our team has a few inside jokes.',
				'Please keep it a small set, otherwise threads get messy.',
				'A thumbs up would replace half of the "+1" replies.',
				'Showing who reacted on hover would be nice.',
				'I like that it is limited, it keeps things tidy.',
			],
			'op' => [
				[0, 'I completely missed the smiley icon, thanks.'],
				[4, 'Hover to see who reacted would be my top request then.'],
			],
		],
		[
			'theme' => 'feedback',
			'title' => 'Better search filters would help',
			'body' => 'Search works, but in a big forum I want to filter by author, category and date. Is anything like that planned?',
			'replies' => [
				'Filtering by category would already be a huge help.',
				'Date range would be my top pick. Old answers are often outdated.',
				'Searching in titles only would be useful too.',
				'Exact phrase search with quotes works already, try it.',
				'+1 for author filter, I often look for something a specific colleague wrote.',
				'Sorting results by newest would cover most of what I need.',
			],
			'op' => [
				[3, 'Quotes for exact phrases works, nice tip!'],
				[5, 'Collected these into one request: category, author, date range and sorting.'],
			],
		],

		// Off-topic
		[
			'theme' => 'offtopic',
			'title' => 'What are you having for lunch today?',
			'body' => 'Lunch thread! I brought leftover curry and it smells amazing. What is everyone else having?',
			'replies' => [
				'Sandwich from the bakery downstairs, as always.',
				'Salad, trying to be good this week.',
				'Ramen! The place across the street finally reopened.',
				'Forgot my lunch at home, so vending machine crackers. Send help.',
				'Homemade lentil soup.',
				'Leftover pizza, no regrets.',
				'Curry sounds good, now I am jealous.',
				'Sushi, it is Friday.',
			],
			'op' => [
				[2, 'The ramen place reopened? Going tomorrow.'],
				[3, 'Someone please share lunch with the crackers person.'],
			],
		],
		[
			'theme' => 'offtopic',
			'title' => 'Weekend plans?',
			'body' => 'Finally a long weekend coming up. Anyone doing anything fun?',
			'replies' => [
				'Hiking if the weather holds.',
				'Painting the living room. Not fun, but necessary.',
				'Visiting family, lots of food.',
				'Absolutely nothing, and I am looking forward to it.',
				'A friend is getting married, so dancing.',
				'Trying a new bread recipe.',
				'Bike ride along the river.',
			],
			'op' => [
				[3, 'Doing absolutely nothing sounds like the best plan.'],
				[6, 'Enjoy, everyone! See you all on Tuesday.'],
			],
		],
		[
			'theme' => 'offtopic',
			'title' => 'Recommend a podcast',
			'body' => 'Long commute starts next month. Looking for podcast recommendations, any topic.',
			'replies' => [
				'"99% Invisible" for design and architecture stories.',
				'"Hardcore History", but be ready for six hour episodes.',
				'"Radiolab" is always interesting.',
				'"The Rest Is History" if you like British humour.',
				'Language learning podcasts have been great for me.',
				'"Freakonomics Radio", easy to listen to.',
				'+1 for "99% Invisible", never a boring episode.',
			],
			'op' => [
				[1, 'Six hour episodes might be perfect for the commute actually.'],
				[6, 'Subscribed to five of these already. Thanks!'],
			],
		],
		[
			'theme' => 'offtopic',
			'title' => 'Office plant care advice',
			'body' => 'Someone left a sad looking plant on my desk. I have no idea what kind it is or how to keep it alive. Help?',
			'replies' => [
				'Post a photo and someone will know what it is.',
				'Most office plants die from too much water, not too little.',
				'If the leaves are yellow, water less. If they are crispy, water more.',
				'Pothos and snake plants survive almost anything.',
				'Put it near a window, but not in direct sun.',
				'There are apps that identify plants from a photo.',
				'Give it a name. Plants with names get better care, scientifically proven by me.',
			],
			'op' => [
				[5, 'A plant app says it is a pothos. Good news, apparently.'],
				[6, 'Named him Gerald. Moved him near the window and he looks happier already.'],
			],
		],
		[
			'theme' => 'offtopic',
			'title' => 'Board game recommendations for a group of six',
			'body' => 'Hosting a game night for six people, some have never played anything beyond Monopoly. Suggestions?',
			'replies' => [
				'"Codenames", easy to explain and works great with six.',
				'"Wavelength" is a big hit with non-gamers.',
				'"Ticket to Ride" is a classic starter game.',
				'"Just One" is cooperative and everyone laughs a lot.',
				'Avoid anything with a 40 page rulebook for the first night.',
				'"Dixit" if your group likes creative games.',
				'+1 for "Codenames".',
			],
			'op' => [
				[6, '"Codenames" keeps coming up, ordering it.'],
				[6, 'Game night was a success, "Just One" was the favourite. Thanks!'],
			],
		],

		// Announcements
		[
			'theme' => 'announcements',
			'title' => 'Planned maintenance this Saturday',
			'body' => "Heads up: the server will be unavailable this Saturday from 08:00 to 10:00 for an upgrade. Please save your work before then.\n\nQuestions welcome below.",
			'replies' => [
				'Thanks for the advance notice!',
				'Will the desktop client just pick up again afterwards?',
				'Is it the big version upgrade?',
				'Saturday morning is perfect, nobody is working then anyway.',
				'Will there be a list of what changes after the upgrade?',
				'Good luck, hope it goes smoothly.',
				'Does this affect the calendar sync too?',
			],
			'op' => [
				[1, 'Yes, the clients reconnect on their own.'],
				[6, 'Calendar sync pauses during the upgrade and catches up afterwards.'],
				[6, 'Upgrade is done, everything is back up. A summary of the changes is coming next week.'],
			],
		],
		[
			'theme' => 'announcements',
			'title' => 'New team members joining in March',
			'body' => 'Please welcome four new colleagues joining us in March! They will introduce themselves in the coming weeks. Make them feel at home.',
			'replies' => [
				'Welcome, everyone!',
				'Exciting, which teams are they joining?',
				'Looking forward to meeting them.',
				'Do we need to set up anything for them in advance?',
				'Welcome aboard!',
				'Point them to the introduce yourself thread.',
			],
			'op' => [
				[1, 'Two in support, one in design and one in finance.'],
				[3, 'Their accounts are ready, so nothing to do on your side.'],
			],
		],
		[
			'theme' => 'announcements',
			'title' => 'Summer event: save the date',
			'body' => 'Our summer event is on the last Friday of June. Food, drinks and a few short talks. Mark your calendars, details follow.',
			'replies' => [
				'Can partners come along?',
				'Will there be vegetarian options?',
				'Love it, last year was great.',
				'Can we suggest talks?',
				'Is it at the same venue as last year?',
				'Marked in my calendar.',
			],
			'op' => [
				[1, 'Partners are welcome, and yes, vegetarian and vegan options.'],
				[3, 'Talk proposals are open, reply here with a title and a sentence about it.'],
			],
		],
	];
}
