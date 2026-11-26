=== eLearningTheme LMS ===
Contributors: mortimer01
Tags: lms, courses, lessons, quiz, education
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight courses for WordPress: lessons, quizzes graded on the server, progress and a student dashboard. No page builder.

== Description ==

eLearningTheme LMS turns a WordPress site into a small school. Teachers build courses from sections, lessons and quizzes; students enrol, work through the lessons, take quizzes and pick up where they left off.

It is made to be light. Pages are rendered on the server, the plugin sends students no JavaScript, and progress lives in its own indexed tables, so a lesson page needs at most five extra database queries.

= What you get =

* **Courses, sections and lessons.** Video lessons (YouTube, Vimeo or your own .mp4) and text lessons, with free preview lessons for visitors.
* **A course builder** in the WordPress admin: drag lessons and quizzes into sections.
* **Quizzes** with multiple-choice and true/false questions. Answers are checked on the server and are never sent to the browser. Students see a review of every answer and can try again.
* **Progress.** A "Mark as complete" button, a progress bar and a course-completed screen.
* **Student management.** Courses > Students lists everyone in a course with their progress, and lets you add or remove students by hand or import a whole class from a CSV file.
* **A student dashboard** with "continue where you left off", every course with its progress, and completed courses.
* **A course catalog** with categories, search and an empty state.
* **Block templates** for courses, lessons, quizzes and the catalog, editable in the Site Editor, and blocks you can use anywhere.
* **Privacy and security.** Locked lessons are protected in the page, the REST API, search and embeds. Every form uses a nonce.
* **Designed emails** for enrolment and course completion, in your design's accent colour, with a plain-text version for every message.
* **Translations** included for Spanish, French, German, Portuguese, Italian and Hungarian.
* **A language switcher.** Offer several languages and let each student pick one: the interface and the emails follow their choice.

It works with any block theme and is designed together with the free [eLearningTheme block theme](https://elearningtheme.com/docs/getting-started/install-the-theme/), whose six designs style every LMS screen.

= Getting started =

1. Go to Courses > Lessons and create a few lessons. Choose video or text, and tick "Free preview" for one of them.
2. Go to Courses > Quizzes and add a quiz with its questions.
3. Create a course. In the Curriculum box, add sections and drag the lessons and quiz into them.
4. Visit the course page and enrol as a student. The "My learning" page holds the student dashboard.

To let visitors create accounts, enable "Anyone can register" in Settings > General.

== Frequently Asked Questions ==

= Does it work with my theme? =

Yes, with any block theme. Courses, lessons and quizzes use block templates, which your theme can override with its own single-elt_course.html, single-elt_lesson.html, single-elt_quiz.html and archive-elt_course.html.

= Can I sell courses? =

Not in the free plugin: here every course is free to enrol in, with no limits on courses or students. Selling is in a separate paid add-on, [eLearningTheme LMS Pro](https://elearningtheme.com/#pricing): Stripe and PayPal checkout, subscriptions, invoices, certificates, drip content and reports.

= Where are quiz answers stored? =

In the quiz's private meta on your server. They are not exposed through the REST API and are never printed in the page.

= Does it add tracking or call external services? =

No. The plugin makes no external requests. A video lesson only contacts YouTube or Vimeo if you use one of their links.

== Screenshots ==

1. A video lesson with the course contents and progress.
2. A quiz result with a review of every answer.
3. The student dashboard.
4. The course builder.

== Changelog ==

= 0.2.1 =
* Security: the output of every LMS block is escaped with an allow-list of HTML (filter eltlms_allowed_html for add-ons); a logged-in student's language choice is saved only with a valid nonce; the question editor checks its own nonce.
* Emails no longer carry a style block; the layout works on phones without it.

= 0.2.0 =
* New: students and visitors can choose their language. Tick the languages your school offers under Courses > Languages; a switcher appears in the site header and on My learning, and there is a "Language switcher" block for anywhere else. The buttons, labels and emails follow each person's choice; course content stays as you wrote it. Off until you tick a second language.
* Enrolment and completion emails are written in the student's language.
* New filters eltlms_languages and eltlms_text_domains, action eltlms_locale_switched, and ELTLMS_Lang::as_user() for add-ons that send email.
* The choice is stored in a cookie (eltlms_lang, one year) for visitors and in the account for logged-in students.

= 0.1.11 =
* Faster course lists: the catalog, the course-list block and My learning now load in a fixed number of database queries, however many courses there are (the catalog of a six-course school went from 26 extra queries to 4, My learning from 49 to 12). New function ELTLMS_Data::prime_courses().

= 0.1.10 =
* Emails show the Site Logo at the top when the site has one (filter eltlms_email_logo).
* Lessons and quizzes can have a featured image; a video lesson uses it as the poster before playback.
* The course picture now heads the enrol box on the course page, and My learning shows it next to each course and above Continue learning.
* A quiz or a reading lesson with a featured image shows it under the title.
* My learning greets the student by first name.

= 0.1.9 =
* Courses > Students: import students from a CSV file (comma or semicolon, Excel or Google Sheets). Existing accounts are enrolled; new email addresses can get an account and a link to set a password. A summary shows who was added, who was already enrolled and which lines were skipped.
* Courses > Students: Reset progress lets a student take a course again from the start. Earlier quiz attempts stay in the history but no longer count. New function ELTLMS_Data::restart() and action eltlms_course_restarted.

= 0.1.8 =
* New filter eltlms_dashboard_sections: add-ons can add sections to the My learning page (Pro: subscriptions).

= 0.1.7 =
* Every quiz attempt now stores the questions exactly as the student saw them, so the review always matches the attempt.
* Attempts have a status: graded, waiting for review or time ran out (database update runs automatically).
* New hooks for add-ons: eltlms_question_types, eltlms_quiz_questions, eltlms_quiz_question_count, eltlms_quiz_facts, eltlms_quiz_gate, eltlms_quiz_before_form, eltlms_quiz_form_note, eltlms_quiz_submit_check, eltlms_quiz_can_retry, eltlms_render_question_input, eltlms_grade_question, eltlms_admin_post_types; actions eltlms_quiz_graded and eltlms_quiz_reviewed.
* The question editor can be reused by add-ons (question banks).

= 0.1.6 =
* New screen: Courses > Students. See who is enrolled in a course, with progress and completion date, search by name or email, add a student by email or username, and remove one (their progress is kept).
* The Students count in the course list links to that screen.
* ELTLMS_Data::enrol() has an optional $force argument for enrolments made by staff.
* Translations for the new screen.

= 0.1.5 =
* New for add-ons: the eltlms_enrol_cta filter (for example a "Buy course" button) and ELTLMS_Data::unenrol(), which keeps progress.

= 0.1.4 =
* Designed HTML emails in one shared template that uses the active design's accent colour: enrolment confirmation and course completed, with a plain-text part. WordPress's password-reset and new-account emails use the same template.
* Translations for the new emails.

= 0.1.3 =
* The course catalog block can show one teacher's courses (author attribute).
* New filters for add-ons: eltlms_teacher_url, eltlms_completed_links, eltlms_locked_cta.

= 0.1.2 =
* New filter eltlms_lock_reason: add-ons can show why a lesson is locked in the curriculum.

= 0.1.1 =
* Readme: contributor is now the WordPress.org account that submitted the plugin.

= 0.1.0 =
* First release: courses, sections, lessons, quizzes, progress, student dashboard, catalog, block templates and translations.
