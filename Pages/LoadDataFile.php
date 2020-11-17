<?php
/**
 * Created by IntelliJ IDEA.
 * User: eloh
 * Date: 2020-01-31
 * Time: 14:54
 */

namespace Stanford\CtSurgery\ThvCasePresentationLoader;
?>

<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://code.jquery.com/jquery-3.3.1.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"
            integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl"
            crossorigin="anonymous"></script>

    <style>
        body {
            width: 90%;
            height: 100px;
            padding: 5px;

        }
    </style>

</head>
<body>
<h3>
    Load Data file
</h3>


<?php

/** @var \Stanford\CtSurgery\ThvCasePresentationLoader\ThvCasePresentationLoader $module */

use REDCap;
use \DateTime;


$url = $module->getUrl("Pages/LoadDataFile.php");
$pid = isset($_GET['pid']) && !empty($_GET['pid']) ? $_GET['pid'] : null;
$recordFieldName = REDCap::getRecordIdField();

for ($fileindex = 0; $fileindex < count($_FILES['uploads']['name']); $fileindex++) {

    //echo '<li>' . $_FILES['uploads']['name'][$fileindex] . '</li>';

    if (isset($_FILES['uploads']['tmp_name'][$fileindex])) {
        $filetype = $_FILES['uploads']['type'][$fileindex];
        $tmpName = $_FILES['uploads']['tmp_name'][$fileindex];
        // for excel spreadsheets, we need to read the data on each sheet
        if (contains($filetype,"spreadsheetml")) {
            $data = [];
            $data['record_id'] = findMaxRecordId($recordFieldName) + 1;

            if ( $xlsx = SimpleXLSX::parse($tmpName) ) {
                $sheet = $xlsx->rows(0); // we only want the first sheet
                $lastrow0 = '';
                $add_comments = '';
                $unknown ='';
                foreach ( $sheet as $rowindex => $row ) {
                    $row[0] = trim(strip_nonascii($row[0]));
                    $row[1] = trim(strip_nonascii($row[1]));
                    $row[2] = trim(strip_nonascii($row[2]));
                    //echo 'lastrow0 \"' .$lastrow0 . '\" currentrow0 \"'
                    //    . $row[0] . '\" row1 \"' . $row[1] . '\" row2 \"' .$row[2].'\"'.PHP_EOL;

                    if (contains($row[0],'Patient Name')) {
                        $data['last_name'] = substr(strrchr($row[1], " "), 1);
                        $data['first_name'] = substr($row[1], 0, strrpos($row[1], ' '));;
                    } else if (contains($row[0],'MRN')) {
                        $data['mrn'] = standardize_mrn($row[1]);
                    } else if (contains($row[0],'Proposed Treatment')) {
                        $data['proposed_date'] =  redcap_date_format($row[1]);
                    } else if (contains($row[0],'Referring MD')) {
                        $data['referring_md']=$row[1];
                    } else if (contains($row[0],'Primary MD')) {
                        $data['primary_md']= $row[1];
                    } else if (contains($row[0],'Procedure MD')) {
                        $data['procedure_md']= $row[1];
                    } else if (contains($row[0],'Clinic MD')) {
                        $data['clinic_md']= $row[1];

                    } else if (contains($row[0],'RN')) {
                        $data['rn']= $row[1];
                    } else if ($row[0]==='Age') {
                        $data['age']= rtrim($row[1],' year old');
                    } else if (contains($row[0],'Gender') && $row[1] === 'male') {
                        $data['gender']= 1;
                    } else if (contains($row[0],'Gender') && $row[1] === 'female') {
                        $data['gender']= 2;
                    } else if (contains($row[0],'History:')) {
                        $data['mh_note']=$row[1];
                        $data = parse_note($row[1], $data);

                    } else if (contains($row[0],'PFT') && contains($row[1],'FEV1')) {
                        $vals = get_paren_vals($row[2]);
                        $data['fev1']= $vals[0];
                        if (sizeof($vals) > 1) {
                            $data['fev1_pct'] = rtrim($vals[1],'%');
                        }
                    } else if (contains($lastrow0,'PFT') && contains($row[1],'DLCO')) {
                        $vals = get_paren_vals($row[2]);
                        $data['dlco'] = $vals[0];
                        if (sizeof($vals) > 1) {
                            $data['dlco_pct'] = rtrim($vals[1],'%');
                        }
                    } else if (contains($row[0],'Anticoagulation')) {
                        $data['anticoag_history'] = $row[2];
                        $lower = strtolower($row[2]);
                        if (contains($row[2],'ASA') || contains($lower,'aspirin')) {
                            $data['anticoagulant___1'] =  1;
                        }
                        if (contains($lower,'plavix')) {
                            $data['anticoagulant___2'] =  1;
                        }
                        if (contains($lower,'coumadin')) {
                            $data['anticoagulant___3'] =  1;
                        }
                        if (contains($lower,'xarelto')) {
                            $data['anticoagulant___4'] =  1;
                        }
                        if (contains($lower,'eliquis')) {
                            $data['anticoagulant___5'] =  1;
                        }
                        if (contains($lower,'heparin')) {
                            $data['anticoagulant___6'] =  1;
                        }
                        if (contains($lower,'warfarin')) {
                            $data['anticoagulant___7'] =  1;
                        }
                    } else if (contains($row[0],'Frailty') && contains($row[1],'BMI')) {
                        $data['bmi']= $row[2];
                    } else if (contains($lastrow0,'Frailty') && contains($row[1],'Serum Albumin')) {
                        $vals = get_paren_vals($row[2]);
                        $data['serum_albumin']=$vals[0];
                        if (sizeof($vals) > 1) {
                            $data['serum_albumin_pm'] =$vals[1];
                        }
                    } else if (contains($lastrow0,'Frailty') && contains($row[1],'ADLs')) {
                        $vals = get_paren_vals($row[2]);
                        $data['adls']=$vals[0];
                        if (sizeof($vals) > 1) {
                            $data['adls_pm']= $vals[1];
                        }
                    } else if (contains($lastrow0,'Frailty') && contains($row[1],'Grip Strength')) {
                        $vals = get_paren_vals($row[2]);
                        $data['grip_strength']= $vals[0];
                        if (sizeof($vals) > 1) {
                            $data['grip_strength_pm']= $vals[1];
                        }
                    } else if (contains($lastrow0,'Frailty') && contains($row[1],'5m WT')) {
                        $vals = get_paren_vals($row[2]);
                        $data['fivem_wt']= $vals[0];
                        if (sizeof($vals) > 1) {
                            $data['fivem_wt_pm'] = $vals[1];
                        }
                    } else if (contains($lastrow0,'Frailty') && contains($row[1],'Score')) {
                        $data['frailty_score'] =  $row[2];
                    } else if (contains($row[0],'STS')) {
                        $data['sts'] = $row[1] * 100;
                    } else if ($row[0]==='EF') {
                        $data['sts_ef'] = $row[1]  * 100;
                    } else if (contains($lastrow0,'STS')) {
                        $data['sts_risk_factors']= $row[1];
                        $data = parse_note($row[1], $data);
                    } else if (contains($lastrow0,'EF') && empty($row[0])) {
                        $data['sts_notes']=$row[1];
                    } else if (contains($row[0],'Echo') && contains($row[1],'Date')) {
                        $data['echo_date'] = redcap_date_format($row[2]);
                    } else if (contains($lastrow0,'Echo') && contains($row[1],'Misc. Notes')) {
                        $data['echo_misc_notes']=$row[2];
                    } else if (contains($lastrow0,'Echo') && contains($row[1],'AVAI')) {
                        $data['avai']= strip_units($row[2]);
                    } else if (contains($lastrow0,'Echo') && contains($row[1],'AVA')) {
                        $data['ava']= strip_units($row[2]);
                    } else if (contains($lastrow0,'Echo') && contains($row[1],'V2 Max')) {
                        $data['v2_max']= strip_units($row[2]);
                    } else if (contains($lastrow0,'Echo') && contains($row[1],'Mean Gradient')) {
                        $data['mean_gradient']=strip_units($row[2]);
                    } else if (contains($lastrow0,'Echo') && contains($row[1],'V1/V2')) {
                        $data['v1_v2'] = $row[2];
                    } else if (contains($lastrow0,'Echo') && contains($row[1],'RVSP')) {
                        $data['rvsp'] = strip_units($row[2]);
                    } else if (contains($lastrow0,'Echo') && contains($row[1],'AI')) {
                        $data['echo_ai'] = $row[2];
                    } else if (contains($lastrow0,'Echo') && contains($row[1],'MR')) {
                        $data['echo_mr'] = $row[2];
                    } else if (contains($lastrow0,'Echo') && contains($row[1],'TR')) {
                        $data['echo_tr'] = $row[2];
                    } else if (contains($lastrow0,'Echo') && contains($row[0],'Notes')) {
                        $data['gen_notes'] = $row[2];
                    } else if (contains($row[0],'RHC') && contains($row[1],'Date')) {
                        $data['rhc_date'] = redcap_date_format($row[2]);
                    } else if (contains($lastrow0,'RHC') && contains($row[1],'RA')) {
                        $data['rhc_ra'] = $row[2];
                    } else if (contains($lastrow0,'RHC') && contains($row[1],'RV')) {
                        $data['rhc_rv'] = $row[2];
                    } else if (contains($lastrow0,'RHC') && contains($row[1],'PA')) {
                        $data['rhc_pa'] = $row[2];
                    } else if (contains($lastrow0,'RHC') && contains($row[1],'PCW')) {
                        $data['rhc_pcw'] = $row[2];
                    } else if (contains($lastrow0,'RHC') && contains($row[1],'CO')) {
                        $data['rhc_co'] = $row[2];
                    } else if (contains($lastrow0,'RHC') && contains($row[1],'CI')) {
                        $data['rhc_ci'] = $row[2];
                    } else if (contains($row[0],'Cors') && contains($row[1],'Date')) {
                        $data['cors_date'] = redcap_date_format($row[2]);

                    } else if (contains($lastrow0,'Cors') && contains($row[1],'LM')) {
                        $data['cors_lm'] = $row[2];
                    } else if (contains($lastrow0,'Cors') && contains($row[1],'LAD')) {
                        $data['cors_lad'] = $row[2];
                    } else if (contains(strip_nonascii($row[1]),'LCX')) {
                        // this one has weird stuff going on in row[0]
                        $data['cors_lcx'] = $row[2];
                    } else if (!contains($lastrow0,'Coronary heights')
                        && contains(strip_nonascii($row[1]),'RCA')) {
                        // this one has weird stuff going on in row[0]
                        $data['cors_rca'] = $row[2];
                    } else if (contains(strip_nonascii($row[1]),'Grafts')) {
                        // this one has weird stuff going on in row[0]
                        $data['cors_grafts'] = $row[2];
                    } else if (contains($row[0],'Coronary heights') && contains($row[1],'LCA')) {
                        $data['ch_lca'] = strip_units($row[2]);
                    } else if (contains($lastrow0,'Coronary heights') && contains($row[1],'RCA')) {
                        $data['ch_rca'] = strip_units($row[2]);
                    } else if (contains($row[0],'Vascular access') && contains($row[1],'RCIA')) {
                        $vals = get_x_vals($row[2]);
                        $data['va_rcia_major'] = $vals[0];
                        if (sizeof($vals) > 1) {
                            $data['va_rcia_minor'] = $vals[1];
                        }
                    } else if (contains($lastrow0,'Vascular access') && contains($row[1],'REIA #1')) {
                        $vals = get_x_vals($row[2]);
                        $data['va_reia1_major'] = $vals[0];
                        if (sizeof($vals) > 1) {
                            $data['va_reia1_minor'] = $vals[1];
                        }
                    } else if (contains($lastrow0,'Vascular access') && contains($row[1],'REIA #2')) {
                        $vals = get_x_vals($row[2]);
                        $data['va_reia2_major'] = $vals[0];
                        if (sizeof($vals) > 1) {
                            $data['va_reia2_minor'] = $vals[1];
                        }
                    } else if (contains($lastrow0,'Vascular access') && contains($row[1],'RCFA')) {
                        $vals = get_x_vals($row[2]);
                        $data['va_rcfa_major'] = $vals[0];
                        if (sizeof($vals) > 1) {
                            $data['va_rcfa_minor'] = $vals[1];
                        }
                    } else if (contains($lastrow0,'Vascular access') && contains($row[1],'LCIA')) {
                        $vals = get_x_vals($row[2]);
                        $data['va_lcia_major'] = $vals[0];
                        if (sizeof($vals) > 1) {
                            $data['va_lcia_minor'] = $vals[1];
                        }
                    } else if (contains($lastrow0,'Vascular access') &&
                        (contains(strip_nonascii($row[1]),'LEIA #1') ||
                            contains(strip_nonascii($row[1]),'LEIA#1'))) {
                        $vals = get_x_vals($row[2]);
                        $data['va_leia1_major'] = $vals[0];
                        if (sizeof($vals) > 1) {
                            $data['va_leia1_minor'] = $vals[1];
                        }
                    } else if (contains($lastrow0,'Vascular access') &&
                        contains(strip_nonascii($row[1]),'LEIA #2')) {
                        $vals = get_x_vals($row[2]);
                        $data['va_leia2_major'] = $vals[0];
                        if (sizeof($vals) > 1) {
                            $data['va_leia2_minor'] = $vals[1];
                        }
                    } else if (contains($lastrow0,'Vascular access') && contains($row[1],'LCFA')) {
                        $vals = get_x_vals($row[2]);
                        $data['va_lcfa_major'] = $vals[0];
                        if (sizeof($vals) > 1) {
                            $data['va_lcfa_minor'] = $vals[1];
                        }
                    } else if (contains($row[0],'SOV Diameters') && contains($row[1],'RCC')) {
                        $data['sov_rcc'] = strip_units($row[2]);
                    } else if (contains($lastrow0,'SOV Diameters')
                        && contains($row[1],'LCC')) {
                        $data['sov_lcc'] = strip_units($row[2]);
                    } else if (contains($lastrow0,'SOV Diameters')
                        && contains($row[1],'NCC')) {
                        $data['sov_ncc'] = strip_units($row[2]);
                    } else if (contains($row[0],'SOV heights > 15 mm')) {
                        $data['sov_heights'] = ($row[1]==='Yes') ?  1 : 0;
                    } else if (contains($row[0],'Ascending Ao diameter') &&
                        contains($row[1],'Long Axis')) {
                        $data['aad_long_axis'] = strip_units($row[2]);
                    } else if (contains($lastrow0,'Ascending Ao diameter') &&
                        contains($row[1],'Short Axis')) {
                        $data['aad_short_axis'] = strip_units($row[2]);
                    } else if (contains($row[0],'Annulus') &&
                        contains($row[1],'Ave Diameter')) {
                        $data['an_ave_diameter'] = strip_units($row[2]);
                    } else if (contains($lastrow0,'Annulus') &&
                        contains($row[1],'Long Axis')) {
                        $data['an_long_axis'] = strip_units($row[2]);
                    } else if (contains($lastrow0,'Annulus') &&
                        contains($row[1],'Short Axis')) {
                        $data['an_short_axis'] = strip_units($row[2]);
                    } else if (contains($lastrow0,'Annulus') &&
                        contains($row[1],'Area')) {
                        $data['an_area'] = strip_units($row[2]);
                    } else if (contains($lastrow0,'Annulus') &&
                        contains($row[1],'Perimeter')) {
                        $data['an_perimeter'] = strip_units($row[2]);
                    } else if (contains($row[0],'Surgical Risk')){
                        if (contains($row[1],'Intermediate') || contains($row[1],'IR')) {
                            $data['surgical_risk'] = 1;
                        } else if (contains($row[1],'Minor') || contains($row[1],'Low Risk')
                            || contains($row[1],'LR')) {
                            $data['surgical_risk'] = 0;
                        } else if (contains($row[1],'High Risk') || contains($row[1],'HR')) {
                            $data['surgical_risk'] = 2;
                        } else if (contains($row[1],'Extreme') || contains($row[1],'ER')) {
                            $data['surgical_risk'] = 3;
                        } else if (contains(strtolower($row[1]),'technically inoperable')) {
                            $data['surgical_risk'] = 4;
                        }
                    } else if (contains($row[0],'Clinical Notes')) {
                        $data['clinicial_notes'] = strip_nonascii($row[1]);
                    } else if (contains($row[0],'Study/ Comm')) {
                        $data['study_comm'] = $row[1];
                    } else if ($row[0]==='Procedure') {
                        $data['procedure'] = $row[1];
                        if (contains(strtolower($row[1]), 'tavr')) {
                            $data['procedure_type'] =  1;
                        } else if (contains(strtolower($row[1]), 'mvr')) {
                            $data['procedure_type'] =  2;
                        }
                        if (contains(strtolower($row[1]), 'valve-in-valve')) {
                            $data['valve_in_valve'] =  1;
                        } else {
                            $data['valve_in_valve'] =  0;
                        }
                    } else if (contains($row[0],'Access')) {
                        $data['access'] =  $row[1];
                        if (contains(strtolower($row[1]), 'right')) {
                            $data['access_side'] =  1;
                        } else if (contains(strtolower($row[1]), 'left')) {
                            $data['access_side'] =  2;
                        }
                        if (contains(strtolower($row[1]), 'transfemoral')) {
                            $data['access_type'] =  1;
                        } else if (contains(strtolower($row[1]), 'transeptal') || contains(strtolower($row[1]), 'transseptal')) {
                            $data['access_type'] =  2;
                        } else if (contains(strtolower($row[1]), 'transapical')) {
                            $data['access_type'] =  3;
                        }
                    } else if (contains($row[0],'Valve')) {
                        $data['valve'] = $row[1];
                    } else if ($row[0]==='Add\'l procedure') {
                        $data['addl_procedure'] = $row[1];
                    } else if (contains($row[0],'Fast Track Eligibility')) {
                        $data['fast_track'] = $row[1];
                    } else if (contains($row[0],'OR Nursing coverage')) {
                        $data['or_nursing'] = $row[1];
                    } else if (contains($row[0],'Comment')) {
                        $add_comments .= $row[1] . '; ';
                    } else {
                        $unknown .= 'row0:'.$row[0].' row1:'.$row[1].' row2:'.$row[2];
                    }
                    if (!empty($row[0])) {
                        $lastrow0=$row[0];
                    }
                }
                $data['comment_addl_notes'] = $add_comments;
                // check if this mrn is already in project; if so, don't add it.
                $filter = "[mrn]='" . $data['mrn'] .
                    "' and [proposed_date]='" . $data['proposed_date'] ."'";
                $duplicate = REDCap::getData(PROJECT_ID, 'array', null,
                    array($recordFieldName), null, null, null, null, null,
                    $filter);
                if (empty($duplicate)) {
                    //echo json_encode(array($data));
                    $return = REDCap::saveData($pid, 'json', json_encode(array($data)));
                    if (empty($return['errors'])) {
                        echo 'Saved THV presentation for ' . $data['mrn'] . ' on ' . $data['proposed_date'] . '<br/>';
                    } else {
                        echo 'Error saving ' . $data['mrn']. ': ';
                        print_r($return['errors']);
                        echo '<br/>';
                    }
                } else {
                    echo $data['mrn'] . ' on ' .$data['proposed_date']. 'record already exists in project' . '<br/>';
                }


            } else {
                $message = SimpleXLSX::parseError();
                print 'xls file parse error ' . $_FILES['uploads']['name'][$fileindex] .   $message. '<br/>';
            }
        }

    } else {
        //send an error
        print 'file not loaded ' . $_FILES['uploads']['name'][$fileindex]. '<br/>';
    }

}

function standardize_mrn($mrn) {
    $tmp = preg_replace('/-/','', $mrn);
    return str_pad($tmp, 8, '0', STR_PAD_LEFT);
}

function strip_nonascii($str) {
    if (!empty($str)) {
        return preg_replace('/[^\x20-\x7E]/', '', $str);
    }
    return "";
}

function strip_units(string $str) {
    return strip_nonascii(preg_replace('/mm2|cm2\/m2|cm2|mmHg|mm|kg|g\/dL|m\/s|ml|mL|L\/min\/m2|L\/min|L/',
        '', $str));
}

// convenience function to get rid of parenthesis and units
// returns an array
function get_paren_vals(string $str) {
    $vals = explode('(', $str);
    $return_vals = [];

    foreach ($vals as $val) {
        $val = rtrim(trim(strip_nonascii($val)),')');
        if ($val ==='-') {
            $return_vals[] = 0;
        } else if ($val ==='+') {
            $return_vals[] = 1;
        } else {
            $return_vals[] = strip_units($val);
        }
    }
    return $return_vals;
}

function parse_note($note, $data) {
    if (contains($note, 'HTN')) {
        $data['mh___1'] = 1;
    }
    if (contains($note, 'HLD')) {
        $data['mh___2'] = 1;
    }
    if (contains($note, 'DM')) {
        $data['mh___3'] = 1;
    }
    if (contains($note, 'CVD')) {
        $data['mh___4'] = 1;
    }
    if (contains($note, 'PVD')) {
        $data['mh___5'] = 1;
    }
    if (contains($note, 'CAD')) {
        $data['mh___6'] = 1;
    }
    if (contains($note, 'COPD')) {
        $data['mh___7'] = 1;
    }
    if (contains(strtolower($note), 'PAF') || contains(strtolower($note), 'afib')) {
        $data['mh___8'] = 1;
    }
    if (contains($note, 'OSA') || contains(strtolower($note), 'apnea')) {
        $data['mh___9'] = 1;
    }
    if (contains($note, 'CVA') || contains($note, 'TIA')) {
        $data['mh___10'] = 1;
    }
    if (contains($note, 'CHF') || contains(strtolower($note), 'congestive heart fail')) {
        $data['mh___11'] = 1;
    }
    if (contains($note, 'ESRD')) {
        $data['mh___12'] = 1;
    }
    if (contains($note, 'CKD')) {
        $data['mh___13'] = 1;
    }
    if (contains(strtolower($note), 'symptomatic as') || contains($note, 'severe as')
        || contains($note, 'bioprosthetic as')) {
        $data['mh___14'] = 1;
    }
    if (contains($note, 'MI')) {
        $data['mh___15'] = 1;
    }
    // lung, bladder, cell, colon
    if (preg_match('/(breast|prostate|bladder|lung|colon|cell) ca/', strtolower($note))
        || contains(strtolower($note), 'carcinoma') || contains(strtolower($note), 'chemo')
        || contains(strtolower($note), 'xrt') || contains(strtolower($note), 'lymphoma')
        || contains(strtolower($note), 'melanoma')
        || contains(strtolower($note), 'hodgkin')
        || contains(strtolower($note), 'leukemia')
        || contains(strtolower($note), 'radiation')) {
        $data['mh___16'] = 1;
    }
    if (contains($note, 'DOE') || contains(strtolower($note), 'dyspnea')
        || contains($note, 'SOB') || contains(strtolower($note), 'shortness of breath')) {
        $data['mh___17'] = 1;
    }
    if (contains($note, 'PNA') || contains($note, 'pneumonia')) {
        $data['mh___18'] = 1;
    }
    if (contains($note, ' PE') || contains($note, 'pulmonary embol')) {
        $data['mh___19'] = 1;
    }
    if (contains($note, ' PAD')) {
        $data['mh___20'] = 1;
    }
    if (contains(strtolower($note), 'asthma')) {
        $data['mh___21'] = 1;
    }
    if (contains(strtolower($note), 'edema')) {
        $data['mh___22'] = 1;
    }
    if (contains(strtolower($note), 'gerd')) {
        $data['mh___23'] = 1;
    }
    if (contains(strtolower($note), 'phtn') || contains(strtolower($note), 'pulmonary htn')
        || contains(strtolower($note), 'pulmonary hypertension')) {
        $data['mh___24'] = 1;
    }
    if (contains(strtolower($note), 'hypothyroid')) {
        $data['mh___25'] = 1;
    }
    if (contains(strtolower($note), 'cardiomy') || contains(strtolower($note), 'ischemic c')
        ||contains($note, 'ICM')) {
        $data['mh___26'] = 1;
    }

    if (contains($note, 'PCI')) {
        $data['mh_proc___1'] = 1;
    }
    if (contains($note, 'CABG')) {
        $data['mh_proc___2'] = 1;
    }
    if (contains($note, 'AVR')) {
        $data['mh_proc___3'] = 1;
    }
    if (contains($note, 'MVR')) {
        $data['mh_proc___4'] = 1;
    }
    if (contains($note, 'PPM') || contains($note, 'pacemaker')) {
        $data['mh_proc___5'] = 1;
    }
    if (contains($note, 'ICD')) {
        $data['mh_proc___6'] = 1;
    }
    if (contains($note, ' kg')) {
        $matches = array();
        preg_match('/([0-9.]+) kg/', $note, $matches);
        $data['weight'] = $matches[1];
    }
    if (contains($note, ' cm')) {
        $matches = array();
        preg_match('/([0-9.]+) cm/', $note, $matches);
        $data['height'] = $matches[1];
    }
    if (contains($note, 'BSA')) {
        $matches = array();
        preg_match('/BSA ([0-9.]+)/', $note, $matches);
        $data['bsa'] = $matches[1];
    }
    if (contains($note, 'Cr ')) {
        $matches = array();
        preg_match('/Cr ([0-9.]+)/', $note, $matches);
        $data['cr'] = $matches[1];
    }
    if (contains($note, 'NYHA Class')) {
        $matches = array();
        preg_match('/NYHA Class ([IV]+)/', $note, $matches);
        if ($matches[1] === 'IV') {
            $data['nyha'] = 4;
        } else if ($matches[1] === 'III') {
            $data['nyha'] = 3;
        } else if ($matches[1] === 'II') {
            $data['nyha'] = 2;
        } else if ($matches[1] === 'I') {
            $data['nyha'] = 1;
        }
    }
    if (contains(strtolower($note), 'caucas')) {
        $data['race'] = 1;
    }  else if (contains(strtolower($note), 'asian')) {
        $data['race'] = 4;
    } else if (contains(strtolower($note), 'fiji') || contains(strtolower($note), 'pacific isl')
        || contains(strtolower($note), 'tongan')) {
        $data['race'] = 5;
    } // put this one last since sometimes aortic aneurysm is abbrev AA
    else if (contains(strtolower($note), 'african') ||contains($note, 'AA')) {
        $data['race'] = 3;
    }
    if (contains(strtolower($note), 'hispanic')|contains(strtolower($note), 'latino')) {
        $data['race'] = 2;
    }
    return $data;
}

// convenience function to split x values
// returns an array
function get_x_vals(string $str) {
    $vals = explode('x', $str);
    $return_vals = [];
    foreach ($vals as $val) {
        $return_vals[] = trim($val);
    }
    return $return_vals;
}

function redcap_date_format($date_to_format) {
    $date_to_format = trim($date_to_format);
    if (contains($date_to_format,'00:00:00')){
        return str_replace(" 00:00:00","",$date_to_format);
    }
    if (contains($date_to_format, '/') &&
        contains($date_to_format, '-')) {
        $date_to_format = trim(explode('-', $date_to_format)[0]);
    }
    if (preg_match('/\d{1,2}\/\d{1,2}\/\d{2,4}/', $date_to_format)) {
        $date_year = explode('/', $date_to_format)[2];
        if (strlen(trim($date_year)) == 4) {
            return (DateTime::createFromFormat('m/d/Y',
                $date_to_format)->format('Y-m-d'));
        } else if (strlen(trim($date_year)) == 2) {
            return (DateTime::createFromFormat('m/d/y',
                $date_to_format)->format('Y-m-d'));
        }

        // unrecognized format
    } else {
        //echo 'unknown date format ' . $date_to_format;
    }

    return $date_to_format;

}


/**
 * This function will create the next record label based on the inputs from the config file
 * and the existing records.
 *
 * @param $recordFieldName - fieldname in project of record id
 * @return string - new record label
 */
function findMaxRecordId($recordFieldName) {

    $record_field_array = array($recordFieldName);
    $recordIdData = REDCap::getData(PROJECT_ID, 'array', null, $record_field_array, null, null, null, null, null, null);
    $recordIDs = array_keys($recordIdData);

    // Retrieve the max value so we can add one to create the new record label
    return max($recordIDs);
}


?>


<form method="post" action="<?php echo $url; ?>" enctype="multipart/form-data">

    <p><strong>Use shift to select multiple files; max of 20 files per submit</strong></p>
    <input name="uploads[]" type="file" multiple="multiple" />
    <input type="submit" value="Save" />
</form>


</body>
</html>
