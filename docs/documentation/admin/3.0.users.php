<?php require('../common/body_header.inc.php'); ?>

<h2>3. Users</h2>
	<p>The Users section allows the managing of students, instructors, and administrators. Note that administrators are not 
considered regular users of the system; an administrator account can not normally be used to login to a course. For the 
purposes of documentation the term "users" will be reserved for any account type that is <em>not</em> an administrator.</p>	
<p>There are four types of user accounts that can exist in an ATutor installation, as defined by their Status:</p>	<dl>
		<dt>Disabled</dt>
		<dd>Only administrators may disable an account. Disabled accounts cannot sign-in to your ATutor installation, but may still 
appear as enrolled in courses.</dd>
		<dt>Unconfirmed</dt>
		<dd>Unconfirmed accounts are created only when the <a href="2.2.system_preferences.php">System Preferences</a> <em>Require 
Email Confirmation Upon Registration</em> option is enabled.</dd>
		<dt>Student</dt>
		<dd>A regular account which can enroll, but not create courses.</dd>

		<dt>Instructor</dt>
		<dd>A regular account which can enroll as well as create courses.</dd>
	</dl>
<h3>3.0.1 Creating User Accounts</h3>
<p>If necessary administrators can manually add users to the system by using <em>Create User Account</em>. When accounts are
created manually they are automatically confirmed and the account status is set to Student, Instructor,or disabled as
choosen in the Account Status field of the user account creation form. </p>
<p>User accounts can also be created by
individuals using the Registration form available through the public pages of ATutor. Instructors can also generate user 
accounts by importing by importing a course list.</p>

<?php require('../common/body_footer.inc.php'); ?>