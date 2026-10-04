<?php
declare(strict_types=1);

function contractReviewState(mixed $raw, array $fields, array $facts, array $previous = []): array {
    if(is_string($raw)) {
        if(strlen($raw)>500000) throw new InvalidArgumentException('Zbyt duży stan akceptacji umowy.');
        $raw=json_decode($raw,true);
    }
    if($raw!==null && !is_array($raw)) throw new InvalidArgumentException('Niepoprawny stan akceptacji umowy.');
    $submitted=$raw??[]; $review=[];
    foreach(array_replace($previous,$submitted) as $key=>$entry) {
        if(!contractPackageApplicable($key,$facts)) continue;
        if(in_array($key,['consumerDocuments','dataProcessingTerms'],true) && trim($facts[$key]??'')==='') continue;
        if(!contractPublicationFieldApplicable($key,$facts)) continue;
        if($key==='consumerDocuments' && !in_array($facts['clientType']??'',['consumer','protected'],true)) continue;
        if($key==='dataProcessingTerms' && ($facts['dataRole']??'')!=='processor') continue;
        if(!array_key_exists($key,contractFields())&&!array_key_exists($key,contractFacts())) continue;
        if($key==='ipPayment'||($key==='rightsTerms'&&($facts['ipMode']??'')!=='exclusive'&&($facts['ipMode']??'')!=='nonexclusive')) continue;
        if(!is_array($entry)||!is_string($entry['value']??null)) throw new InvalidArgumentException('Niepoprawny stan pola umowy.');
        $value=(string)($fields[$key]??$facts[$key]??'');
        $accepted=($entry['accepted']??false)===true && trim($entry['value'])===trim($value);
        $review[$key]=['value'=>$value,'accepted'=>$accepted];
    }
    return $review;
}
function contractRequiredReview(array $fields,array $facts): array {
    $required=array_keys($fields);
    if(trim($fields['paymentDetails']??'')==='') $required=array_values(array_diff($required,['paymentDetails']));
    foreach(contractFacts() as $key=>$field) {
        if(!contractPackageApplicable($key,$facts)) continue;
        if(in_array($key,['consumerDocuments','dataProcessingTerms'],true) && trim($facts[$key]??'')==='') continue;
        if($key==='ipPayment' || ($key==='rightsTerms' && ($facts['ipMode']??'')==='transfer')) continue;
        if(in_array($key,['clientTaxId','domainRegistrar','serverTarget'],true) && trim($facts[$key]??'')==='') continue;
        if(!contractPublicationFieldApplicable($key,$facts)) continue;
        if($key==='consumerDocuments' && !in_array($facts['clientType']??'',['consumer','protected'],true)) continue;
        if($key==='dataProcessingTerms' && ($facts['dataRole']??'')!=='processor') continue;
        $required[]=$key;
    }
    return $required;
}
function contractPendingReview(array $review): array {
    $pending=[];
    foreach($review as $key=>$entry) if(!$entry['accepted']) $pending[]=contractFields()[$key]??contractFacts()[$key]['label'];
    return $pending;
}
