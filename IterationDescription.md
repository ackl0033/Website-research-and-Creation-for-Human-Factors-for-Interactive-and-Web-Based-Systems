Looking at some of the results in the survey that was done there are a few specific worded answers that relate to some issues with the website in terms of usability. These were consisted of, not explaining that the password needed to be a minimum of 8 letters long, allowing emails to be entered and parsed through without a @ symbol, not being able to find someone’s profile to message them from the message tab and not displaying availability on the profile for what times they can help.

Design, HTML, CSS
-	The login and sign-up pages had a short descriptive sentence added to explain what format the password should be in (minimum of 8 characters long). This helps with the frustration of entering a password to only then be told that it needs to be 8 characters long.
-	Images were added to all the tabs as well as some of the pages such as home and search. This helps with visual clarity allowing less stress on the eyes creating a more enjoyable time which can help boost the future lookbacks on the website.
-	On each of the descriptive features that can be changed, empty states and placeholders were added explaining what they are for and what to do with them. This helps with some confusion on how these descriptions affect their overall profile and requests.
-	A red circle was added to the messages word on the tab that displays the number of unread messages. This helps with clarity and sticks out being easy to see.

JavaScript
-	Email fields are stricter enforcing the email to be valid with a @ symbol. This helps with accidental inputs of emails as well as security being enforced ensuring it’s a valid email address.
-	Profile and Search tabs properly show each of their descriptions and will state whether the current person hasn’t updated a section or added anything. This reduces confusion as well as consistency across all skill requests.

PHP
-	Initailise JSON arrays like skills offered and skills requested with blank statements and will update when changed. This ensures that its consistent between every new profile.
-	Update number of messages haven’t been viewed when a message has been received and will reset to 0 when the Boolean has been switched by the messages being viewed. This helps with clarity of when a new message has come through.

MySQL
-	Added fields for message notifications such as a Boolean if messages have been viewed or not and another field for how many messages haven’t been viewed since last time. Makes it easier to access for later ensuring consistency and accuracy.
