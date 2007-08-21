<?php
/****************************************************************/
/* ATutor														*/
/****************************************************************/
/* Copyright (c) 2002-2007 by Greg Gay & Joel Kronenberg        */
/* Adaptive Technology Resource Centre / University of Toronto  */
/* http://atutor.ca												*/
/*                                                              */
/* This program is free software. You can redistribute it and/or*/
/* modify it under the terms of the GNU General Public License  */
/* as published by the Free Software Foundation.				*/
/****************************************************************/
// $Id$
define('AT_INCLUDE_PATH', '../include/');
require(AT_INCLUDE_PATH.'vitals.inc.php');
require(AT_INCLUDE_PATH.'lib/test_result_functions.inc.php');
require(AT_INCLUDE_PATH.'classes/testQuestions.class.php');

$tid = intval($_REQUEST['tid']);

//make sure max attempts not reached, and still on going
$sql		= "SELECT *, UNIX_TIMESTAMP(start_date) AS start_date, UNIX_TIMESTAMP(end_date) AS end_date FROM ".TABLE_PREFIX."tests WHERE test_id=".$tid." AND course_id=".$_SESSION['course_id'];
$result= mysql_query($sql, $db);
$test_row = mysql_fetch_assoc($result);
/* check to make sure we can access this test: */
if (!$test_row['guests'] && ($_SESSION['enroll'] == AT_ENROLL_NO || $_SESSION['enroll'] == AT_ENROLL_ALUMNUS)) {
	require(AT_INCLUDE_PATH.'header.inc.php');
	$msg->printInfos('NOT_ENROLLED');

	require(AT_INCLUDE_PATH.'footer.inc.php');
	exit;
}

if (!$test_row['guests'] && !authenticate_test($tid)) {
	header('Location: my_tests.php');
	exit;
}

$out_of = $test_row['out_of'];
if (!$test_row['random']) {
	$sql	= "SELECT COUNT(*) AS num_questions FROM ".TABLE_PREFIX."tests_questions_assoc WHERE test_id=$tid";
	$result = mysql_query($sql, $db);
	if ($row = mysql_fetch_assoc($result)) {
		$test_row['num_questions'] = $row['num_questions'];
	} // else 0
}	

$sql		= "SELECT COUNT(*) AS cnt FROM ".TABLE_PREFIX."tests_results WHERE status=1 AND test_id=".$tid." AND member_id=".$_SESSION['member_id'];
$takes_result= mysql_query($sql, $db);
$takes = mysql_fetch_assoc($takes_result);	

if ( (($test_row['start_date'] > time()) || ($test_row['end_date'] < time())) || 
   ( ($test_row['num_takes'] != AT_TESTS_TAKE_UNLIMITED) && ($takes['cnt'] >= $test_row['num_takes']) )  ) {
	require(AT_INCLUDE_PATH.'header.inc.php');
	$msg->printErrors('MAX_ATTEMPTS');
	require(AT_INCLUDE_PATH.'footer.inc.php');
	exit;
}

if (!isset($_GET['pos'])) {
	$pos = 0; // first question
} else {
	$pos = abs($_GET['pos']);
}

$max_pos = 0;

// get and check for a valid result_id. if there is none then get all the questions and insert them as in progress.
// note: for guests the result_id is stored in session, but no need to really know that here.
$result_id = get_test_result_id($tid, $max_pos);

if ($result_id == 0) {
	// there is no test in progress, yet.
	// init this test.

	// simple safety op to make sure nothing is being posted (as it shouldn't!)
	// $_POST = array(); // don't need this because of the else-if
	// basically, shouldn't be able to post to this page if there isn't a valid result_id first (how can someone post an answer
	// to a question they haven't viewed? [unless they're trying to 'hack' something])

	$result_id = init_test_result_questions($tid, (bool) $test_row['random'], $test_row['num_questions']);

	if (!$_SESSION['member_id']) {
		// this is a guest, so we store the result_id in SESSION
		$_SESSION['test_result_id'] = $result_id;
	}

	$pos = 0; // force to always start at the first question
		
} else if (isset($_POST['next']) || isset($_POST['previous'])) {
	// if the test isn't time limited, then what happens when only a few questions are answered? the test result
	// will be inconsistant.
	// need to keep track of the max(pos) answered, so that we know if a question is being re-answered.
	// store 'max_pos' in session or db or form?

	// assuming only one question is displayed	
	$question_id = intval(key($_POST['answers']));

	// get the old score (incase this question is being re-answered)
	$sql = "SELECT score FROM ".TABLE_PREFIX."tests_answers WHERE result_id=$result_id AND question_id=$question_id";
	$result = mysql_query($sql, $db);
	$row = mysql_fetch_assoc($result);
	$old_score = $row['score'];

	$score = 0;

	$sql = "SELECT TQA.weight, TQA.question_id, TQ.type, TQ.answer_0, TQ.answer_1, TQ.answer_2, TQ.answer_3, TQ.answer_4, TQ.answer_5, TQ.answer_6, TQ.answer_7, TQ.answer_8, TQ.answer_9 FROM ".TABLE_PREFIX."tests_questions_assoc TQA INNER JOIN ".TABLE_PREFIX."tests_questions TQ USING (question_id) WHERE TQA.test_id=$tid AND TQA.question_id=$question_id";
	$result	= mysql_query($sql, $db);

	if ($row = mysql_fetch_assoc($result)) {
		if (isset($_POST['answers'][$row['question_id']])) {
			$obj = TestQuestions::getQuestion($row['type']);
			$score = $obj->mark($row);

			$sql	= "UPDATE ".TABLE_PREFIX."tests_answers SET answer='{$_POST[answers][$row[question_id]]}', score='$score' WHERE result_id=$result_id AND question_id=$row[question_id]";
			mysql_query($sql, $db);
		}
	}

	$pos++;

	// update the final score
	// update status to complate to fix refresh test issue.
	if ($pos > $max_pos) {
		$sql	= "UPDATE ".TABLE_PREFIX."tests_results SET final_score=final_score + $score, date_taken=date_taken, end_time=NOW(), max_pos=$pos WHERE result_id=$result_id";
		$result	= mysql_query($sql, $db);
	} else {
		// this question has already been answered, so we have to re-mark it, which means finding the OLD score for this question and adjusting
		// $score with the positive or negative difference.
		// no need to update max_pos b/c we're only updating a previously answered question.

		$score = $old_score - $score;

		$sql	= "UPDATE ".TABLE_PREFIX."tests_results SET final_score=final_score - $score, date_taken=date_taken, end_time=NOW() WHERE result_id=$result_id";
		$result	= mysql_query($sql, $db);
	}

	if ($pos >= $test_row['num_questions']) {
		// end of the test.
		$sql	= "UPDATE ".TABLE_PREFIX."tests_results SET status=1, date_taken=date_taken, end_time=NOW() WHERE result_id=$result_id";
		$result	= mysql_query($sql, $db);

		$msg->addFeedback('ACTION_COMPLETED_SUCCESSFULLY');
		if (!$_SESSION['enroll']) {
			header('Location: ../tools/view_results.php?tid='.$tid.SEP.'rid='.$result_id);		
			exit;
		}
		header('Location: ../tools/my_tests.php');
		exit;
	}
	
	if (isset($_POST['previous'])) {
		$pos-=2;
		if ($pos < 0) {
			$pos = 0;
		}
	}

	header('Location: '.$_SERVER['PHP_SELF'].'?tid='.$tid.SEP.'pos='.$pos);
	exit;
}

if (defined('AT_FORCE_GET_FILE') && AT_FORCE_GET_FILE) {
	$content_base_href = 'get.php/';
} else {
	$course_base_href = 'content/' . $_SESSION['course_id'] . '/';
}

require(AT_INCLUDE_PATH.'header.inc.php');

/* Retrieve the content_id of this test */
$num_questions = $test_row['num_questions'];
$content_id = $test_row['content_id'];
$anonymous = $test_row['anonymous'];
$instructions = $test_row['instructions'];
$title = $test_row['title'];

$_letters = array(_AT('A'), _AT('B'), _AT('C'), _AT('D'), _AT('E'), _AT('F'), _AT('G'), _AT('H'), _AT('I'), _AT('J'));

// this is a kludge to get the question number incremented.
// a diff approach could be to pass the position to the display() method.
for ($i = 0; $i < $pos; $i++) {
	TestQuestionCounter(true);
}

// retrieve the test questions that were saved to `tests_answers`
if ($test_row['random']) {
	$sql	= "SELECT R.*, A.*, Q.* FROM ".TABLE_PREFIX."tests_answers R INNER JOIN ".TABLE_PREFIX."tests_questions_assoc A USING (question_id) INNER JOIN ".TABLE_PREFIX."tests_questions Q USING (question_id) WHERE R.result_id=$result_id AND A.test_id=$tid ORDER BY Q.question_id LIMIT $pos, 1";
} else {
	$sql	= "SELECT R.*, A.*, Q.* FROM ".TABLE_PREFIX."tests_answers R INNER JOIN ".TABLE_PREFIX."tests_questions_assoc A USING (question_id) INNER JOIN ".TABLE_PREFIX."tests_questions Q USING (question_id) WHERE R.result_id=$result_id AND A.test_id=$tid ORDER BY A.ordering, Q.question_id LIMIT $pos, 1";
}
$result	= mysql_query($sql, $db);
$question_row = mysql_fetch_assoc($result);

if (!$result || !$question_row) {
	echo '<p>'._AT('no_questions').'</p>';
	require(AT_INCLUDE_PATH.'footer.inc.php');
	exit;
}

?>
<form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?pos=<?php echo $pos; ?>">
<input type="hidden" name="tid" value="<?php echo $tid; ?>" />
<div class="input-form" style="width:80%">
	<div class="row"><h2><?php echo $title; ?></h2></div>
	<?php
	// retrieve the answer to re-populate the form (so we can edit our answer)
	$sql = "SELECT answer FROM ".TABLE_PREFIX."tests_answers WHERE result_id=$result_id AND question_id=$question_row[question_id]";
	$result = mysql_query($sql, $db);
	$row = mysql_fetch_assoc($result);
	
	$obj = TestQuestions::getQuestion($question_row['type']);
	$obj->display($question_row, $row['answer']);

	?>
	<div class="row buttons">
		<?php if ($pos > 0): ?>
			<input type="submit" name="previous" value="<?php echo _AT('previous'); ?>" />
		<?php endif; ?>
		<input type="submit" name="next" value="<?php echo _AT('next'); ?>" accesskey="s" />
	</div>
</div>
</form>
<script type="text/javascript">
//<!--
function iframeSetHeight(id, height) {
	document.getElementById("qframe" + id).style.height = (height + 20) + "px";
}
//-->
</script>
<?php require(AT_INCLUDE_PATH.'footer.inc.php'); ?>