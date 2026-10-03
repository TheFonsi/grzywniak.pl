<?php
require_once __DIR__.'/../api/offer-readiness.php';
$question='Does the client provide photos?';
$session=['internalAnalysis'=>['missingInformation'=>['  '.$question.'  ']],'adminDecisions'=>[$question=>['source'=>'HUMAN','answer'=>'Yes']]];
if(!missingInformationManuallyConfirmed($session)) throw new RuntimeException('Trimmed human decision should satisfy the offer gate.');
$session['adminDecisions'][$question]['answer']=' ';
if(missingInformationManuallyConfirmed($session)) throw new RuntimeException('Empty human answer must not satisfy the offer gate.');
$session['adminDecisions'][$question]=['source'=>'AI_AUTO','answer'=>'Suggested'];
if(missingInformationManuallyConfirmed($session)) throw new RuntimeException('AI_AUTO must not satisfy the manual offer gate.');
echo "Offer readiness tests passed.\n";
