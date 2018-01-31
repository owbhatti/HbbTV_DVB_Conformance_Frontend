<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

function HbbTV_DVB_mpdvalidator($dom, $hbbtv, $dvb) {
    
    if($dvb){
        DVB_mpdvalidator($dom);
    }
    
    if($hbbtv){
        HbbTV_mpdvalidator($dom);
    }
    
}

function DVB_mpdvalidator($dom){
    global $locate;
    
    $mpdreport = fopen($locate . '/mpdreport.txt', 'a+b');
    
    $mpd_string = $dom->saveXML();
    $mpd_bytes = strlen($mpd_string);
    if($mpd_bytes > 256*1024){
        fwrite($mpdreport, "**'DVB check violated: Section 4.5- The MPD size after xlink resolution SHALL NOT exceed 256 Kbytes', found " . ($mpd_bytes/1024) . " Kbytes.\n");
    }
    
    $MPD = $dom->getElementsByTagName('MPD')->item(0);
    $profiles = $MPD->getAttribute('profiles');
    if(strpos($profiles, 'urn:dvb:dash:profile:dvb-dash:2014') === FALSE){
        fwrite($mpdreport, "**'DVB check violated: Section 4.1- The URN for the profile (MPEG Interoperability Point) shall be \"urn:dvb:dash:profile:dvb-dash:2014\"', specified profile could not be found.\n");
    }
    
    // Periods within MPD
    $period_count = 0;
    $adapt_audio_count = 0; $main_audio_found = false;
    $type = $MPD->getAttribute('type');
    $AST = $MPD->getAttribute('availabilityStartTime');
    foreach($MPD->childNodes as $node){
        
        if($type == 'dynamic' || $AST != ''){
            if($node->nodeName == 'UTCTiming'){
                $acceptedTimingURIs = array('urn:mpeg:dash:utc:ntp:2014', 'urn:mpeg:dash:utc:http-head:2014', 'urn:mpeg:dash:utc:http-xsdate:2014',
                    'urn:mpeg:dash:utc:http-iso:2014','urn:mpeg:dash:utc:http-ntp:2014');
                if(!(in_array($node->getAttribute('schemeIdURI'), $acceptedTimingURIs))){
                    fwrite($mpdreport, "**'Warning for DVB check: Section 4.7.2- If the MPD is dynamic or if the MPD@availabilityStartTime is present then the MPD SHOULD countain at least one UTCTiming element with the @schemeIdURI attribute set to one of the following: $acceptedTimingURIs ', could not be found in the provided MPD.\n");
                }
            }
        }
        
        if($node->nodeName == 'Period'){
            $period_count++;
            
            foreach ($node->childNodes as $child){
                
                if($child->nodeName == 'SegmentList'){
                    fwrite($mpdreport, "**'DVB check violated: Section 4.2.2- The Period.SegmentList shall not be present', but found in Period $period_count.\n");
                }
            }
            
            // Adaptation Sets within each Period
            $adapts = $node->getElementsByTagName('AdaptationSet');
            $adapts_len = $adapts->length;
            
            if($adapts_len > 16){
                fwrite($mpdreport, "**'DVB check violated: Section 4.5- The MPD has a maximum of 16 adaptation sets per period', found $adapts_len in Period $period_count.\n");
            }
            
            for($i=0; $i<$adapts_len; $i++){
                $adapt_video_count = 0; 
                $main_video_found = false;
                $adapt_width_present = true; $adapt_height_present = true; $adapt_frameRate_present = true;
                
                $adapt = $adapts[$i];
                
                $reps = $adapt->getElementsByTagName('Representation');
                $reps_len = $reps->length;
                if($reps_len > 16){
                    fwrite($mpdreport, "**'DVB check violated: Section 4.5- The MPD has a maximum of 16 representations per adaptation set', found $reps_len in Period $period_count Adaptation Set " . ($i+1) . ".\n");
                }
                
                if($adapt->getAttribute('contentType') == 'video'){
                    $adapt_video_count++;
                    
                    if($adapt->getAttribute('maxWidth') == ''){
                        fwrite($mpdreport, "**'Warning for DVB check: Section 4.4- For any Adaptation Sets with @contentType=\"video\" @maxWidth attribute SHOULD be present', could not be found in Period $period_count Adaptation Set " . ($i+1) . ".\n");
                    } 
                    if($adapt->getAttribute('maxHeight') == ''){
                        fwrite($mpdreport, "**'Warning for DVB check: Section 4.4- For any Adaptation Sets with @contentType=\"video\" @maxHeight attribute SHOULD be present', could not be found in Period $period_count Adaptation Set " . ($i+1) . ".\n");
                    }
                    if($adapt->getAttribute('maxFrameRate') == ''){
                        fwrite($mpdreport, "**'Warning for DVB check: Section 4.4- For any Adaptation Sets with @contentType=\"video\" @maxFrameRate attribute SHOULD be present', could not be found in Period $period_count Adaptation Set " . ($i+1) . ".\n");
                    }
                    if($adapt->getAttribute('par') == ''){
                        fwrite($mpdreport, "**'Warning for DVB check: Section 4.4- For any Adaptation Sets with @contentType=\"video\" @par attribute SHOULD be present', could not be found in Period $period_count Adaptation Set " . ($i+1) . ".\n");
                    }
                    
                    foreach ($adapt->childNodes as $ch){
                        if($ch->name == 'Role'){
                            if($ch->getAttribute('schemeIdUri') == 'urn:mpeg:dash:role:2011' && $ch->getAttribute('value') == 'main'){
                                $main_video_found = true;
                            }
                        }
                    }
                    
                    // Representations within each Adaptation Set
                    if($adapt->getAttribute('width') == ''){
                        $adapt_width_present = false;
                    }
                    if($adapt->getAttribute('height') == ''){
                        $adapt_height_present = false;
                    }
                    if($adapt->getAttribute('frameRate') == ''){
                        $adapt_frameRate_present = false;
                    }
                    
                    for($j=0; $j<$reps_len; $j++){
                        $rep = $reps[$j];
                        if($adapt_width_present == false && $rep->getAttribute('width') == ''){
                            fwrite($mpdreport, "**'DVB check violated: Section 4.4- For any Representation within an Adaptation Set with @contentType=\"video\" @width attribute SHALL be present if not in the AdaptationSet element', could not be found in neither Period $period_count Adaptation Set " . ($i+1) . " nor Period $period_count Adaptation Set " . ($i+1) . " Representation " . ($j+1) . ".\n");
                        }
                        if($adapt_height_present == false && $rep->getAttribute('height') == ''){
                            fwrite($mpdreport, "**'DVB check violated: Section 4.4- For any Representation within an Adaptation Set with @contentType=\"video\" @height attribute SHALL be present if not in the AdaptationSet element', could not be found in neither Period $period_count Adaptation Set " . ($i+1) . " nor Period $period_count Adaptation Set " . ($i+1) . " Representation " . ($j+1) . ".\n");
                        }
                        if($adapt_width_present == false && $rep->getAttribute('width') == ''){
                            fwrite($mpdreport, "**'DVB check violated: Section 4.4- For any Representation within an Adaptation Set with @contentType=\"video\" @width attribute SHALL be present if not in the AdaptationSet element', could not be found in neither Period $period_count Adaptation Set " . ($i+1) . " nor Period $period_count Adaptation Set " . ($i+1) . " Representation " . ($j+1) . ".\n");
                        }
                        if($adapt->getAttribute('sar') == '' && $rep->getAttribute('sar') == ''){
                            fwrite($mpdreport, "**'Warning for DVB check: Section 4.4- For any Representation within an Adaptation Set with @contentType=\"video\" @sar attribute SHOULD be present or inherited from the Adaptation Set', could not be found in neither Period $period_count Adaptation Set " . ($i+1) . " nor Period $period_count Adaptation Set " . ($i+1) . " Representation " . ($j+1) . ".\n");
                        }
                    }
                }
                elseif($adapt->getAttribute('contentType') == 'audio'){
                    $adapt_audio_count++;
                    
                    $adapt_role_element_found = false;
                    $adapt_audioChannelConfiguration_element_found = false;
                    $adapt_mimeType = $adapt->getAttribute('mimeType');
                    $adapt_codecs = $adapt->getAttribute('mimeType');
                    $adapt_audioSamplingRate = $adapt->getAttribute('audioSamplingRate');
                    $adapt_specific_role_count = 0;
                    foreach($adapt->childNodes as $ch){
                        if($ch->nodeName == 'Role'){
                            $adapt_role_element_found = true;
                            
                            if($ch->getAttribute('schemeIdURI') == 'urn:mpeg:dash:role:2011'){
                                $adapt_specific_role_count++;
                            }
                            if($ch->getAttribute('value') == 'main'){
                                $main_audio_found = true;
                            }
                        }
                        if($ch->nodeName == 'AudioChannelConfiguration'){
                            $adapt_audioChannelConfiguration_element_found = true;
                        }
                    }
                    
                    if($adapt_specific_role_count == 0){
                        fwrite($mpdreport, "**'DVB check violated: Section 6.1.2- Every audio Adaptation Set SHALL include at least one Role Element using the scheme \"urn:mpeg:dash:role:2011\" as defined in ISO/IEC 23009-1', could not be found in Period $period_count Adaptation Set " . ($i+1) . ".\n");
                    }
                    
                    for($j=0; $j<$reps_len; $j++){
                        $rep = $reps[$j];
                        $rep_role_element_found = false;
                        $rep_audioChannelConfiguration_element_found = false;
                        foreach ($rep->childNodes as $ch){
                            if($ch->nodeName == 'Role'){
                                $rep_role_element_found = true;
                            }
                            if($ch->nodeName == 'AudioChannelConfiguration'){
                                $rep_audioChannelConfiguration_element_found = true;
                            }
                        }
                        
                        if($adapt_role_element_found == false && $rep_role_element_found == false){
                            fwrite($mpdreport, "**'DVB check violated: Section 6.1.1- All audio Representations SHALL either define or inherit the elements and attributes shown in Table 3', Role element could not be found in neither Period $period_count Adaptation Set " . ($i+1) . " nor Period $period_count Adaptation Set " . ($i+1) . " Representation " . ($j+1) . ".\n");
                        }
                        if($adapt_audioChannelConfiguration_element_found == false && $rep_audioChannelConfiguration_element_found == false){
                            fwrite($mpdreport, "**'DVB check violated: Section 6.1.1- All audio Representations SHALL either define or inherit the elements and attributes shown in Table 3', AudioChannelConfiguration element could not be found in neither Period $period_count Adaptation Set " . ($i+1) . " nor Period $period_count Adaptation Set " . ($i+1) . " Representation " . ($j+1) . ".\n");
                        }
                        if($adapt_mimeType == '' && $rep->getAttribute('mimeType') == ''){
                            fwrite($mpdreport, "**'DVB check violated: Section 6.1.1- All audio Representations SHALL either define or inherit the elements and attributes shown in Table 3', mimeType attribute could not be found in neither Period $period_count Adaptation Set " . ($i+1) . " nor Period $period_count Adaptation Set " . ($i+1) . " Representation " . ($j+1) . ".\n");
                        }
                        if($adapt_codecs == '' && $rep->getAttribute('codecs') == ''){
                            fwrite($mpdreport, "**'DVB check violated: Section 6.1.1- All audio Representations SHALL either define or inherit the elements and attributes shown in Table 3', codecs attribute could not be found in neither Period $period_count Adaptation Set " . ($i+1) . " nor Period $period_count Adaptation Set " . ($i+1) . " Representation " . ($j+1) . ".\n");
                        }
                        if($adapt_audioSamplingRate == '' && $rep->getAttribute('audioSamplingRate') == ''){
                            fwrite($mpdreport, "**'DVB check violated: Section 6.1.1- All audio Representations SHALL either define or inherit the elements and attributes shown in Table 3', audioSamplingRate attribute could not be found in neither Period $period_count Adaptation Set " . ($i+1) . " nor Period $period_count Adaptation Set " . ($i+1) . " Representation " . ($j+1) . ".\n");
                        }
                    }
                }
                
                if($adapt_video_count > 1 && $main_video_found == false){
                    fwrite($mpdreport, "**'DVB check violated: Section 4.2.2- If a Period element contains multiple Adaptation Sets with @contentType=\"video\" then at least one Adaptation Set SHALL contain a Role element with @schemeIdUri=\"urn:mpeg:dash:role:2011\" and @value=\"main\"', could not be found in Period $period_count.\n");
                }
            }
        }
        
        if($period_count > 64){
            fwrite($mpdreport, "**'DVB check violated: Section 4.5- The MPD has a maximum of 64 periods after xlink resolution', found $period_count.\n");
        }
    }
    
    if($adapt_audio_count > 1 && $main_audio_found == false){
        fwrite($mpdreport, "**'DVB check violated: Section 6.1.2- If there is more thatn one audio Adaptation Set in a DASH Presentation then at least one of them SHALL be tagged with an @value set to \"main\", could not be found in Period $period_count.\n");                 
    }
}

function HbbTV_mpdvalidator($dom){
    global $locate;
    
    $mpdreport = fopen($locate . '/mpdreport.txt', 'a+b');
    
    $MPD = $dom->getElementsByTagName('MPD')->item(0);
}