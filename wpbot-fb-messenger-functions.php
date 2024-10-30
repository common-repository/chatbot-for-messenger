<?php
/*
* Main function for handleing facebook responses.
*/
function qcpd_wpfb_get_accesstoken_from_id($page_id){
	global $wpdb;
	$table = $wpdb->prefix.'wpbot_fb_pages';
    $page = $wpdb->get_row("SELECT * FROM {$table} where 1 and page_id = '".$page_id."'");
	return $page->page_access_token;
}

function qcld_wpbot_fb_page_details($page_id){
	global $wpdb;
	$table = $wpdb->prefix.'wpbot_fb_pages';
    $page = $wpdb->get_row("SELECT * FROM {$table} where 1 and page_id = '".$page_id."'");
	return $page;
}

function qcpd_wpfb_messenger_callback(){
    
	if(get_option('wpfb_enable_fbbot')!='on'){
		return;
	}
	$default_instruction = strip_tags(get_option('wpfb_default_instruction'));
    if(isset($_GET['action']) && $_GET['action']=='fbinteraction'){
        
        $verify_token = get_option('wpfb_verify_token');
        $hub_verify_token = null;

        if(isset($_REQUEST['hub_challenge'])) {
            $challenge = sanitize_text_field($_REQUEST['hub_challenge']);
            $hub_verify_token = sanitize_text_field($_REQUEST['hub_verify_token']);
        }


        if ($hub_verify_token === $verify_token) {
            echo $challenge;exit;
        }
        $input = json_decode(file_get_contents('php://input'), true);

        $sender = $input['entry'][0]['messaging'][0]['sender']['id'];
        $message = $input['entry'][0]['messaging'][0]['message']['text'];
		$pageId = $input['entry'][0]['id'];
		$access_token = qcpd_wpfb_get_accesstoken_from_id($pageId);

        /**
         * Some Basic rules to validate incoming messages
         */
        if(isset($input['entry'][0]['messaging'][0]['message']) && $message!=''){
            // Normal message response part

			
			
            // Send feedback, Email intent handleing
            if(get_option($sender.'_feedback') && get_option($sender.'_feedback')==1){
                if (filter_var($message, FILTER_VALIDATE_EMAIL)) {
					update_option($sender.'_feedback_email', $message);
                    $jsonData = qcpd_wpfb_email_feedback_2($sender);
                    qcpd_wpfb_send_fb_reply($jsonData, $access_token);exit;

                  } else {

                    $jsonData = qcpd_wpfb_email_feedback_1($sender);
                    qcpd_wpfb_send_fb_reply($jsonData, $access_token);exit;
                    
                  }
                
            }
			
			// Handling site search intent
            if(get_option($sender.'_sitesearch') && get_option($sender.'_sitesearch')==1){
                delete_option($sender.'_sitesearch');
				$sitesearchresult = qcld_wpbo_search_site_fb($message);
				if($sitesearchresult['status']=='success'){

					$searchresults = $sitesearchresult['results'];
					$jsonmsg = '';
					foreach($searchresults as $result){
						$jsonmsg .= '{
							"title":"'.$result['title'].'",
							"image_url":"'.$result['imgurl'].'",
							"default_action": {
								"type": "web_url",
								"url": "'.$result['link'].'",
								"webview_height_ratio": "tall",
							  },
						},';
					}

					$jsonData = '{
						"recipient":{
							"id":"'.$sender.'"
						},
						"message":{
							"attachment":{
							  "type":"template",
							  "payload":{
								"template_type":"generic",
								"elements":[
									'.$jsonmsg.'
								]
							  }
							}
						  }
					}';
					
					qcpd_wpfb_send_fb_reply($jsonData, $access_token);exit;
				}else{
					
					
					$jsonData = qcpd_wpfb_menu_global($sender, $access_token, strip_tags(get_option('wpfb_default_no_match')));
					
					qcpd_wpfb_send_fb_reply($jsonData, $access_token);exit;
				}
                
            }
			
			// Email Subscription intent handleing
            if(get_option($sender.'_subscription') && get_option($sender.'_subscription')==1){
				delete_option($sender.'_subscription');
                if (filter_var($message, FILTER_VALIDATE_EMAIL)) {
                    $userinfo = qcpd_wpfb_userinfo($sender, $access_token);
					$email = $message;
					$jsonData = '{
						"recipient":{
							"id":"'.$sender.'"
						},
						"sender_action":"typing_on"
					}';
					qcpd_wpfb_send_fb_reply($jsonData, $access_token);
					$subscriptionresult = qcld_wbfb_chatbot_email_subscription($userinfo->first_name.' '.$userinfo->last_name, $email);
					sleep(2);
					$jsonData = '{
						"recipient":{
							"id":"'.$sender.'"
						},
						"message":{
							"text":"'.$subscriptionresult['msg'].'"
						}
					}';
					qcpd_wpfb_send_fb_reply($jsonData, $access_token);exit;
                  } else {
                    $jsonData = qcpd_wpfb_email_subscription_1($sender, $access_token);
                    qcpd_wpfb_send_fb_reply($jsonData, $access_token);exit;
                  }
                
            }
            if(get_option($sender.'_feedback') && get_option($sender.'_feedback')==2){
				update_option($sender.'_feedback_msg', $message);
                $jsonData = qcpd_wpfb_email_feedback_3($sender, $access_token);
                qcpd_wpfb_send_fb_reply($jsonData, $access_token);exit;
            }

            //phone intent
            if(get_option($sender.'_phone') && get_option($sender.'_phone')==1){
                $jsonData = qcpd_wpfb_phonenumber_2($sender);
                qcpd_wpfb_send_fb_reply($jsonData, $access_token);exit;
            }

            //code for faq
            if(strtolower($message)=='faq'){
                $jsonData = '{
                    "recipient":{
                        "id":"'.$sender.'"
                    },
                    "sender_action":"typing_on"
                }';
                qcpd_wpfb_send_fb_reply($jsonData, $access_token);
                sleep(2);
                $jsonData = qcpd_wpfb_faq($sender);
                qcpd_wpfb_send_fb_reply($jsonData, $access_token);exit;
            }
			
			//code for menu
			if(strtolower($message)=='menu' || strtolower($message)=='help' || strtolower($message)=='start'){
				
				delete_option($sender.'_feedback');
				delete_option($sender.'_phone');
				delete_option($sender.'_sitesearch');
				delete_option($sender.'_subscription');
				
				$jsonData = '{
                    "recipient":{
                        "id":"'.$sender.'"
                    },
                    "sender_action":"typing_on"
                }';
                qcpd_wpfb_send_fb_reply($jsonData, $access_token);
                sleep(2);
				$jsonData = qcpd_wpfb_menu($sender, $access_token);
                qcpd_wpfb_send_fb_reply($jsonData, $access_token);exit;
			}
			if(strtolower($message)=='get started'){
				$userinfo = qcpd_wpfb_userinfo($sender, $access_token);
				$jsonData = '{
					"recipient":{
						"id":"'.$sender.'"
					},
					"sender_action":"typing_on"
				}';
				qcpd_wpfb_send_fb_reply($jsonData, $access_token);
				sleep(2);
				$jsonData = '{
                    "recipient":{
                        "id":"'.$sender.'"
                    },
                    "message":{
                        "text":"Hi '.$userinfo->last_name.', '.$default_instruction.'"
                    }
                }';
                qcpd_wpfb_send_fb_reply($jsonData, $access_token);exit;
			}
			
			
			//code for faq
            if(strtolower($message)==strtolower(get_option('wpfb_command_live_agent'))){
				
				qcpd_wpfb_pass_thread_control($sender, $access_token);
				
				$msg = get_option('wpfb_contact_admin_text');
				
                $jsonData = '{
                    "recipient":{
                        "id":"'.$sender.'"
                    },
                    "sender_action":"typing_on"
                }';
                qcpd_wpfb_send_fb_reply($jsonData, $access_token);
                sleep(2);
                $jsonData = '{
                    "recipient":{
                        "id":"'.$sender.'"
                    },
                    "message":{
                        "text":"'.$msg.'"
                    }
                }';
                qcpd_wpfb_send_fb_reply($jsonData, $access_token);exit;
            }
			
            //dialogflow part
            // sending typing_on response
            $jsonData = '{
                "recipient":{
                    "id":"'.$sender.'"
                },
                "sender_action":"typing_on"
            }';
            qcpd_wpfb_send_fb_reply($jsonData, $access_token);
            sleep(2);

            //get reply for the msg from df
            
			
			if(get_option('wp_chatbot_df_api') && get_option('wp_chatbot_df_api')=='v2'){
				$jsonData = qcpd_wpfb_get_response_from_dfv2($message, $sender, $access_token);
			}else{
				$jsonData = qcpd_wpfb_get_response_from_df($message, $sender, $access_token);
			}
            
            //prepairing the jsondata for facebook
            qcpd_wpfb_send_fb_reply($jsonData, $access_token);exit;
            
        }elseif(isset($input['entry'][0]['messaging'][0]['postback'])){
            //Postback button response handleing part.

            $postbacktitle = $input['entry'][0]['messaging'][0]['postback']['title'];
            $postbackpayload = $input['entry'][0]['messaging'][0]['postback']['payload'];
            $all_faqs = unserialize( get_option('support_query'));
            //FOR faq answer payload
            if(in_array($postbackpayload, $all_faqs)){
                $faqkey = array_search ($postbackpayload, $all_faqs);
                $faqans = unserialize(get_option('support_ans'));

                $jsonData = '{
                    "recipient":{
                        "id":"'.$sender.'"
                    },
                    "sender_action":"typing_on"
                }';
                qcpd_wpfb_send_fb_reply($jsonData, $access_token);
                sleep(2);

                $jsonData = '{
                    "recipient":{
                        "id":"'.$sender.'"
                    },
                    "message":{
                        "text":"'.strip_tags($faqans[$faqkey]).'"
                    }
                }';
                qcpd_wpfb_send_fb_reply($jsonData, $access_token);exit;
            }elseif(strtolower($postbackpayload)==strtolower(get_option('qlcd_wp_chatbot_sys_key_support') != '' ? get_option('qlcd_wp_chatbot_sys_key_support') : 'FAQ')){
				
				$jsonData = '{
                    "recipient":{
                        "id":"'.$sender.'"
                    },
                    "sender_action":"typing_on"
                }';
                qcpd_wpfb_send_fb_reply($jsonData, $access_token);
                sleep(2);
                $jsonData = qcpd_wpfb_faq($sender);
                qcpd_wpfb_send_fb_reply($jsonData, $access_token);exit;
				
			}elseif(strtolower($postbackpayload)==strtolower(get_option('qlcd_wp_site_search') != '' ? get_option('qlcd_wp_site_search') : 'Site Search')){
				
				$jsonData = '{
                    "recipient":{
                        "id":"'.$sender.'"
                    },
                    "sender_action":"typing_on"
                }';
                qcpd_wpfb_send_fb_reply($jsonData, $access_token);
                sleep(2);
                $jsonData = qcpd_wpfb_site_search_1($sender, $access_token);
                qcpd_wpfb_send_fb_reply($jsonData, $access_token);exit;
				
			}elseif(strtolower($postbackpayload)=='qc-first-handshake'){
				$userinfo = qcpd_wpfb_userinfo($sender, $access_token);
				$jsonData = '{
					"recipient":{
						"id":"'.$sender.'"
					},
					"sender_action":"typing_on"
				}';
				qcpd_wpfb_send_fb_reply($jsonData, $access_token);
				sleep(2);
				$jsonData = '{
                    "recipient":{
                        "id":"'.$sender.'"
                    },
                    "message":{
                        "text":"Hi '.$userinfo->last_name.', '.$default_instruction.'"
                    }
                }';
                qcpd_wpfb_send_fb_reply($jsonData, $access_token);exit;
				
			}elseif(strtolower($postbackpayload)== strtolower(get_option('qlcd_wp_chatbot_support_phone'))){
				$jsonData = qcpd_wpfb_phonenumber_1($sender);
				qcpd_wpfb_send_fb_reply($jsonData, $access_token);exit;
			}elseif(strtolower($postbackpayload)== strtolower(get_option('qlcd_wp_chatbot_support_email'))){
				$jsonData = qcpd_wpfb_email_feedback_1($sender);
				qcpd_wpfb_send_fb_reply($jsonData, $access_token);exit;
			}else{
                $postbacktitle = $input['entry'][0]['messaging'][0]['postback']['title'];
                $postbackpayload = $input['entry'][0]['messaging'][0]['postback']['payload'];
                $jsonData = '{
                    "recipient":{
                        "id":"'.$sender.'"
                    },
                    "sender_action":"typing_on"
                }';
                qcpd_wpfb_send_fb_reply($jsonData, $access_token);
                sleep(2);

                
				
				if(get_option('wp_chatbot_df_api') && get_option('wp_chatbot_df_api')=='v2'){
					$jsonData = qcpd_wpfb_get_response_from_dfv2($postbackpayload, $sender, $access_token);
				}else{
					$jsonData = qcpd_wpfb_get_response_from_df($postbackpayload, $sender, $access_token);
				}
				
                qcpd_wpfb_send_fb_reply($jsonData, $access_token);exit;
            }

        }else{
			
			$type = $input['entry'][0]['changes'][0]['field'];
			$verb = $input['entry'][0]['changes'][0]['value']['verb'];

			if($type=='feed' && $verb == 'add' ){
				
				$fromID = $input['entry'][0]['changes'][0]['value']['from']['id'];
				$fromName = $input['entry'][0]['changes'][0]['value']['from']['name'];
				$message = $input['entry'][0]['changes'][0]['value']['message'];
				$postID = $input['entry'][0]['changes'][0]['value']['post_id'];
				$commentID = $input['entry'][0]['changes'][0]['value']['comment_id'];
				$parent_id = $input['entry'][0]['changes'][0]['value']['parent_id'];
				$postcontent = qcwp_get_fbpost_content($postID, $access_token);
				
				
				$checkposts = get_posts(array(
					'numberposts'	=> -1,
					'post_type'		=> 'wpfbposts',
					'meta_key'		=> 'fb_post_id',
					'meta_value'	=> $postID
				));
				
				if(!empty($checkposts) && $fromID !== $pageId && $postID == $parent_id ){
					
					$post_id = ($checkposts[0]->ID);
					
					$enable_private_reply_from_df = get_post_meta( $post_id, 'enable_private_reply_from_df', true );
					$enable_comment_reply_from_df = get_post_meta( $post_id, 'enable_comment_reply_from_df', true );
					
					$comment_reply = get_post_meta( $post_id, 'comment_reply', true );
					$comment_reply_is_condition = get_post_meta( $post_id, 'comment_reply_is_condition', true );
					$comment_reply_condition = get_post_meta( $post_id, 'comment_reply_condition', true );
					$comment_condition_value = get_post_meta( $post_id, 'comment_condition_value', true );
					$comment_reply_text = get_post_meta( $post_id, 'comment_reply_text', true );
					
					$private_reply = get_post_meta( $post_id, 'private_reply', true );
					$private_reply_condition = get_post_meta( $post_id, 'private_reply_condition', true );
					$reply_condition = get_post_meta( $post_id, 'reply_condition', true );
					$condition_value = get_post_meta( $post_id, 'condition_value', true );
					$reply_text = get_post_meta( $post_id, 'reply_text', true );
					
					//private Replies
					
						
					if($private_reply=='on'){
						$send_reply = false;
						if($private_reply_condition==1){
							$send_reply = qcwpfb_is_condition_valid($reply_condition, $condition_value, $message);
							
						}else{
							$send_reply = true;
						}
						
						if($send_reply){
							
							//remove html & line breaks as it does not support
							$reply_text = strip_tags( $reply_text );
							$breaks = array("\r\n", "\n", "\r");
							$reply_text = str_replace($breaks, "", $reply_text);

							$reply_text = str_replace(array('[sender_name]', '[sender_comment]'),array($fromName, $message), $reply_text);
							
							$jsonData = '{
								"recipient":{
									"comment_id":"'.$commentID.'"
								},
								"message":{
									"text":"'.$reply_text.'"
								}
							}';
							qcpd_wpfb_send_fb_reply($jsonData, $access_token);exit;

							
						}
						
						
					}

					//Comment Reply
					if($comment_reply=='on'){
						$send_reply = false;
						if($comment_reply_is_condition==1){
							$send_reply = qcwpfb_is_condition_valid($comment_reply_condition, $comment_condition_value, $message);
						}else{
							$send_reply = true;
						}
						
						if($send_reply){
							$comment_reply_text = str_replace(array('[sender_name]', '[sender_comment]'),array($fromName, $message), $comment_reply_text);
							
							$postfields = "message=".$comment_reply_text."&access_token=$access_token";
							$url = "https://graph.facebook.com/v3.3/$commentID/comments";
							$res = qcwpbot_send_response($postfields, $url);
						}

					}

				}
				
				/*
				$handle = fopen('test2.txt', 'w');
				fwrite($handle, $res);
				fclose($handle);
				*/
				
				
			
			}
			
			
		}
        exit;
    }

}


/* Send reply to Facebook */
function qcpd_wpfb_send_fb_reply($jsonData, $access_token){
	
	$url = 'https://graph.facebook.com/v2.6/me/messages?access_token='.$access_token;
	$jsonDataEncoded = $jsonData;
	$result = wp_remote_post($url, array(
		'headers'   => array(
						'Content-Type' => 'application/json; charset=utf-8'
					),
		'body'      => $jsonDataEncoded,
		'method'    => 'POST'
	));
}

/*
* Get Userinfo
*/
function qcpd_wpfb_userinfo($sender, $access_token){
	return json_decode(wp_remote_fopen('https://graph.facebook.com/'.$sender.'?fields=first_name,last_name,profile_pic&access_token='.$access_token));
}

/* Get reply from dialogflow */
function qcpd_wpfb_get_response_from_df($query='', $sender, $access_token){
	if($query!=''){
		$sessionid = 'qcpd_wpfb_df_session_id';
		$postData = array('query' => array($query), 'lang' => get_option('qlcd_wp_chatbot_dialogflow_agent_language'), 'sessionId' => $sessionid);
		$jsonData = json_encode($postData);
		$v = date('Ymd');
		$result = wp_remote_post('https://api.dialogflow.com/v1/query?v=20170712', array(
			'headers'   => array(
							'Content-Type' => 'application/json; charset=utf-8',
							'Authorization' => 'Bearer '.get_option('qlcd_wp_chatbot_dialogflow_client_token')
						),
			'body'      => $jsonData,
			'method'    => 'POST'
		));

        $result = json_decode($result);
        $intent = $result->result->metadata->intentName;
        if($intent=='Default Fallback Intent'){
            //site search code. Checking if any result exists or not.
            $jsonData = '{
                    "recipient":{
                        "id":"'.$sender.'"
                    },
                    "message":{
                        "text":"'.$result->result->fulfillment->messages[0]->speech.'"
                    }
                }';
                return $jsonData;

        }elseif($intent=='faq'){
            //code for faq intent df
            return qcpd_wpfb_faq($sender);
        }elseif($intent=='email'){
            //feedback, send email intent
            return qcpd_wpfb_email_feedback_1($sender);
        }elseif($intent=='phone'){
            return qcpd_wpfb_phonenumber_1($sender);
        }elseif($intent=='email subscription'){
			return qcpd_wpfb_email_subscription_1($sender, $access_token);
		}elseif(is_object($result->result->fulfillment) && property_exists($result->result->fulfillment, 'messages')){
            //text reponse dialogflow
			if($result->result->fulfillment->messages[0]->type==0){

				if(strip_tags($result->result->fulfillment->messages[0]->speech)!=''){
					$jsonData = '{
						"recipient":{
							"id":"'.$sender.'"
						},
						"message":{
							"text":"'.strip_tags($result->result->fulfillment->messages[0]->speech).'"
						}
					}';
					return $jsonData;
				}else{
					$jsonData = qcpd_wpfb_menu_global($sender, $access_token, strip_tags(get_option('wpfb_default_no_match')));
					return $jsonData;
				}
				
                
            }elseif($result->result->fulfillment->messages[0]->type==2){
                //Quick Reply dialogflow
                $title = strip_tags($result->result->fulfillment->messages[0]->title);
                $replies = $result->result->fulfillment->messages[0]->replies;
                $replyjson = '';
                foreach($replies as $reply){
                    $replyjson .= '{
                        "type":"postback",
                        "title":"'.$reply.'",
                        "payload":"'.$reply.'"
                    },';
                }
                $jsonData = '{
                    "recipient":{
                        "id":"'.$sender.'"
                    },
                    "message":{
                        "attachment":{
                          "type":"template",
                          "payload":{
                            "template_type":"button",
                            "text":"'.$title.'",
                            "buttons":[
                                '.$replyjson.'
                            ]
                          }
                        }
                    }
                }';
                return $jsonData;

            }elseif($result->result->fulfillment->messages[0]->type==1){
                //card response dialogflow
                $jsonmsg = '';
                foreach($result->result->fulfillment->messages as $message){
                    if($message->type==1){
                        $buttons = $message->buttons;
                        $jsonbtn = '';
                        foreach($buttons as $button){
                            $jsonbtn .= '{
                                "type":"web_url",
                                "url":"'.$button->postback.'",
                                "title":"'.$button->text.'"
                            },';
                            
                        }
                        $jsonmsg .= '{
                            "title":"'.$message->title.'",
                            "image_url":"'.$message->imageUrl.'",
                            "subtitle":"'.$message->subtitle.'",
                            
                            "buttons":[
                                '.$jsonbtn.'            
                            ]      
                        },';
                    }
                }
                $jsonData = '{
                    "recipient":{
                        "id":"'.$sender.'"
                    },
                    "message":{
                        "attachment":{
                          "type":"template",
                          "payload":{
                            "template_type":"generic",
                            "elements":[
                                '.$jsonmsg.'
                            ]
                          }
                        }
                      }
                }';
                return $jsonData;
            }
					
        }

	}
}

/* Get reply from dialogflow V2*/
function qcpd_wpfb_get_response_from_dfv2($query='', $sender, $access_token){
	if($query!=''){

		
	
		$result = qc_df_v2_api($query);

        $result = json_decode($result, true);

		
		
		if(isset($result['queryResult']) && !empty($result['queryResult'])){
		
			$intent = $result['queryResult']['intent']['displayName'];
			
			
			
			if($intent=='Default Fallback Intent'){
				
				$jsonData = '{
                    "recipient":{
                        "id":"'.$sender.'"
                    },
                    "message":{
                        "text":"'.(get_option('qlcd_wp_chatbot_dialogflow_defualt_reply') != '' ? get_option('qlcd_wp_chatbot_dialogflow_defualt_reply') : 'Sorry, I did not understand you. You may browse').'"
                    }
                }';
                return $jsonData;


			}elseif($intent=='faq'){
				//code for faq intent df
				return qcpd_wpfb_faq($sender);
			}elseif($intent=='email'){
				//feedback, send email intent
				return qcpd_wpfb_email_feedback_1($sender);
			}elseif($intent=='phone'){
				return qcpd_wpfb_phonenumber_1($sender);
			}elseif($intent=='email subscription'){
				return qcpd_wpfb_email_subscription_1($sender, $access_token);
			}elseif(isset($result['queryResult']['fulfillmentMessages']) && !empty($result['queryResult']['fulfillmentMessages'])){
				
				$dfmessages = $result['queryResult']['fulfillmentMessages'];

				
				foreach($dfmessages as $key => $message){
					
					if(isset($message['text'])){
						//text response
						
						$jsonData = '{
							"recipient":{
								"id":"'.$sender.'"
							},
							"message":{
								"text":"'.strip_tags($message['text']['text'][0]).'"
							}
						}';
						return $jsonData;
						
					}elseif(isset($message['quickReplies'])){
						//quick replies
						
						$title = strip_tags($message['quickReplies']['title']);
						$replies = $message['quickReplies']['quickReplies'];
						$replyjson = '';
						foreach($replies as $reply){
							$replyjson .= '{
								"type":"postback",
								"title":"'.$reply.'",
								"payload":"'.$reply.'"
							},';
						}
						$jsonData = '{
							"recipient":{
								"id":"'.$sender.'"
							},
							"message":{
								"attachment":{
								  "type":"template",
								  "payload":{
									"template_type":"button",
									"text":"'.$title.'",
									"buttons":[
										'.$replyjson.'
									]
								  }
								}
							}
						}';
						return $jsonData;
					}elseif(isset($message['card'])){
						$jsonmsg = '';
						foreach($result['queryResult']['fulfillmentMessages'] as $msg){
							if(isset($msg['card'])){
								$buttons = $msg['card']['buttons'];
								$jsonbtn = '';
								foreach($buttons as $button){
									$jsonbtn .= '{
										"type":"web_url",
										"url":"'.$button['postback'].'",
										"title":"'.$button['text'].'"
									},';
									
								}
								$jsonmsg .= '{
									"title":"'.$msg['card']['title'].'",
									"image_url":"'.$msg['card']['imageUri'].'",
									"subtitle":"'.$msg['card']['subtitle'].'",
									
									"buttons":[
										'.$jsonbtn.'            
									]      
								},';
							}
						}

						$jsonData = '{
							"recipient":{
								"id":"'.$sender.'"
							},
							"message":{
								"attachment":{
								  "type":"template",
								  "payload":{
									"template_type":"generic",
									"elements":[
										'.$jsonmsg.'
									]
								  }
								}
							  }
						}';
						return $jsonData;
						
					}
					
				}

			}
		}else{
			$jsonData = '{
				"recipient":{
					"id":"'.$sender.'"
				},
				"message":{
					"text":"'.(get_option('qlcd_wp_chatbot_dialogflow_defualt_reply') != '' ? get_option('qlcd_wp_chatbot_dialogflow_defualt_reply') : 'Sorry, I did not understand you. You may browse').'"
				}
			}';
			return $jsonData;
			
		}

	}
}




function qcpd_wpfb_faq($sender){
    $faqjson = '';
    $all_faqs = unserialize( get_option('support_query'));
	
	$multiarray = array();
	while(!empty($all_faqs)){
		if(count($all_faqs)>3){
			$multiarray[] = array_slice($all_faqs, 0, 3);
			unset($all_faqs[0]);
			unset($all_faqs[1]);
			unset($all_faqs[2]);
			$all_faqs = array_values($all_faqs);
		}else{
			$multiarray[] = $all_faqs;
			unset($all_faqs);
			$all_faqs = array();
		}
	}

	$elementjson = '';
	foreach($multiarray as $element){
		$buttonjson = '';
		foreach($element as $button){
			$buttonjson .= '{
				"type":"postback",
				"title":"'.$button.'",
				"payload":"'.$button.'"
			},';
		}
		$elementjson .= '{
			"title": "Welcome to FAQ Section",
			"buttons": [
			  '.$buttonjson.'
			]
		  },';
		
	}
	
	$jsonData = '{
		"recipient":{
			"id":"'.$sender.'"
		},
		"message":{
			"attachment":{
			  "type":"template",
			  "payload":{
				"template_type":"generic",
				"elements":[
					'.$elementjson.'
				]
			  }
			}
		  }
	}';
	
    return $jsonData;
}

function qcpd_wpfb_menu_global($sender, $access_token, $title){
	
    $faqjson = '';
	
	$msgtext = unserialize(get_option('qlcd_wp_chatbot_wildcard_msg'));
	$userinfo = qcpd_wpfb_userinfo($sender, $access_token);

	$msgtextoutput = 'I am here to find what you need. What are you looking for?';
	
	$phonetextarray = unserialize(get_option('qlcd_wp_chatbot_support_phone'));
	$phonetxt = $phonetextarray[array_rand($phonetextarray)];
	
    $all_faqs = array(
	
		
		(get_option('qlcd_wp_chatbot_sys_key_support') != '' ? strtoupper(get_option('qlcd_wp_chatbot_sys_key_support')) : 'FAQ'),
		(get_option('qlcd_wp_send_us_email') != '' ? get_option('qlcd_wp_send_us_email') : 'Send Us Email'),

		$phonetxt

	);
	
	
	$multiarray = array();
	while(!empty($all_faqs)){
		if(count($all_faqs)>3){
			$multiarray[] = array_slice($all_faqs, 0, 3);
			unset($all_faqs[0]);
			unset($all_faqs[1]);
			unset($all_faqs[2]);
			$all_faqs = array_values($all_faqs);
		}else{
			$multiarray[] = $all_faqs;
			unset($all_faqs);
			$all_faqs = array();
		}
	}
	$elementjson = '';
	foreach($multiarray as $element){
		$buttonjson = '';
		foreach($element as $button){
			$buttonjson .= '{
				"type":"postback",
				"title":"'.$button.'",
				"payload":"'.$button.'"
			},';
		}
		$elementjson .= '{
			"title": "'.$title.'",
			"buttons": [
			  '.$buttonjson.'
			]
		  },';
		
	}
	


	
	$jsonData = '{
		"recipient":{
			"id":"'.$sender.'"
		},
		"message":{
			"attachment":{
			  "type":"template",
			  "payload":{
				"template_type":"generic",
				"elements":[
					'.$elementjson.'
				]
			  }
			}
		  }
	}';
	
	
    return $jsonData;
}




function qcpd_wpfb_menu($sender, $access_token){
	
    $faqjson = '';

	$userinfo = qcpd_wpfb_userinfo($sender, $access_token);
	
	//$msgtextoutput = 'I am here to find what you need. What are you looking for?';
	
	$msgtext = unserialize(get_option('qlcd_wp_chatbot_wildcard_msg'));
	$msgtextoutput = $msgtext[array_rand($msgtext)];
	
	$phonetxt = get_option('qlcd_wp_chatbot_support_phone');
	if($phonetxt==''){
		$phonetxt = 'Leave your number. We will call you back!';
	}
	
	
    $all_faqs = array(
	
		
		(get_option('qlcd_wp_chatbot_sys_key_support') != '' ? strtoupper(get_option('qlcd_wp_chatbot_sys_key_support')) : 'FAQ'),
		(get_option('qlcd_wp_send_us_email') != '' ? get_option('qlcd_wp_send_us_email') : 'Send Us Email'),

		$phonetxt

	);
	
	
	$multiarray = array();
	while(!empty($all_faqs)){
		if(count($all_faqs)>3){
			$multiarray[] = array_slice($all_faqs, 0, 3);
			unset($all_faqs[0]);
			unset($all_faqs[1]);
			unset($all_faqs[2]);
			$all_faqs = array_values($all_faqs);
		}else{
			$multiarray[] = $all_faqs;
			unset($all_faqs);
			$all_faqs = array();
		}
	}
	$elementjson = '';
	foreach($multiarray as $element){
		$buttonjson = '';
		foreach($element as $button){
			$buttonjson .= '{
				"type":"postback",
				"title":"'.$button.'",
				"payload":"'.$button.'"
			},';
		}
		$elementjson .= '{
			"title": "'.$msgtextoutput.'",
			"buttons": [
			  '.$buttonjson.'
			]
		  },';
		
	}
	


	
	$jsonData = '{
		"recipient":{
			"id":"'.$sender.'"
		},
		"message":{
			"attachment":{
			  "type":"template",
			  "payload":{
				"template_type":"generic",
				"elements":[
					'.$elementjson.'
				]
			  }
			}
		  }
	}';
	
	/*
    $jsonData = '{
        "recipient":{
            "id":"'.$sender.'"
        },
        "message":{
            "attachment":{
                "type":"template",
                "payload":{
                "template_type":"button",
                "text":"'.$msgtextoutput.'",
                "buttons":[
                    '.$faqjson.'
                ]
                }
            }
        }
    }';
	*/
	
    return $jsonData;
}
//for wowbot support
function qcpd_wpfb_support($sender){
    $faqjson = '';
    $all_faqs = unserialize( get_option('support_query'));
	
	$all_faqs = array_merge($all_faqs);
	$multiarray = array();
	while(!empty($all_faqs)){
		if(count($all_faqs)>3){
			$multiarray[] = array_slice($all_faqs, 0, 3);
			unset($all_faqs[0]);
			unset($all_faqs[1]);
			unset($all_faqs[2]);
			$all_faqs = array_values($all_faqs);
		}else{
			$multiarray[] = $all_faqs;
			unset($all_faqs);
			$all_faqs = array();
		}
	}
	
    $elementjson = '';
	foreach($multiarray as $element){
		$buttonjson = '';
		foreach($element as $button){
			$buttonjson .= '{
				"type":"postback",
				"title":"'.$button.'",
				"payload":"'.$button.'"
			},';
		}
		$elementjson .= '{
			"title": "Welcome to Support Section",
			"buttons": [
			  '.$buttonjson.'
			]
		  },';
		
	}

	$jsonData = '{
		"recipient":{
			"id":"'.$sender.'"
		},
		"message":{
			"attachment":{
			  "type":"template",
			  "payload":{
				"template_type":"generic",
				"elements":[
					'.$elementjson.'
				]
			  }
			}
		  }
	}';
    return $jsonData;
}


function qcpd_wpfb_email_feedback_1($sender){
    
    update_option($sender.'_feedback', 1);
    $texts = unserialize(get_option('qlcd_wp_chatbot_asking_email'));
    $jsonData = '{
        "recipient":{
            "id":"'.$sender.'"
        },
        "message":{
            "text":"'.$texts[array_rand($texts)].'"
        }
    }';
    return $jsonData;
}

function qcpd_wpfb_site_search_1($sender, $access_token){
    
    update_option($sender.'_sitesearch', 1);
	
	$userinfo = qcpd_wpfb_userinfo($sender, $access_token);
	$texts = unserialize(get_option('qlcd_wp_chatbot_search_keyword'));
	$msgtextoutput = str_replace('#name',$userinfo->last_name, $texts[array_rand($texts)]);
	
    
    $jsonData = '{
        "recipient":{
            "id":"'.$sender.'"
        },
        "message":{
            "text":"'.$msgtextoutput.'"
        }
    }';
    return $jsonData;
}

function qcpd_wpfb_email_feedback_1_woo($sender){
    
    update_option($sender.'_feedback', 1);
    $texts = unserialize(get_option('qlcd_woo_chatbot_asking_email'));
    $jsonData = '{
        "recipient":{
            "id":"'.$sender.'"
        },
        "message":{
            "text":"'.$texts[array_rand($texts)].'"
        }
    }';
    return $jsonData;
}
function qcpd_wpfb_email_feedback_2($sender){
    update_option($sender.'_feedback', 2);
    $texts = unserialize(get_option('qlcd_wp_chatbot_asking_msg'));
    $jsonData = '{
        "recipient":{
            "id":"'.$sender.'"
        },
        "message":{
            "text":"'.$texts[array_rand($texts)].'"
        }
    }';
    return $jsonData;
}

function qcpd_wpfb_email_feedback_3($sender, $access_token){
    delete_option($sender.'_feedback');
	
	$userinfo = qcpd_wpfb_userinfo($sender, $access_token);
	
	$name = $userinfo->last_name;
	$email = get_option($sender.'_feedback_email');
	$message = get_option($sender.'_feedback_msg');

    $subject = 'Feedback from WPBot by Client';
    //Extract Domain
    $url = get_site_url();
    $url = parse_url($url);
    $domain = $url['host'];
    
    $admin_email = get_option('admin_email');
    $toEmail = get_option('qlcd_wp_chatbot_admin_email') != '' ? get_option('qlcd_wp_chatbot_admin_email') : $admin_email;
    $fromEmail = "wordpress@" . $domain;
    //Starting messaging and status.
    $response['status'] = 'fail';
    $response['message'] = esc_html(str_replace('\\', '',get_option('qlcd_wp_chatbot_email_fail')));

	//build email body
	$bodyContent = "";
	$bodyContent .= '<p><strong>' . esc_html__('Feedback Details', 'wpchatbot') . ':</strong></p><hr>';
	
	$bodyContent .= '<p>' . esc_html__('Name', 'wpfb') . ' : ' . esc_html($name) . '</p>';
	$bodyContent .= '<p>' . esc_html__('Email', 'wpfb') . ' : ' . esc_html($email) . '</p>';
	$bodyContent .= '<p>' . esc_html__('Message', 'wpfb') . ' : ' . esc_html($message) . '</p>';
	
		
	$bodyContent .= '<p>' . esc_html__('Mail Generated on', 'wpchatbot') . ': ' . date('F j, Y, g:i a') . '</p>';
	$to = $toEmail;
	$body = $bodyContent;

	$headers = array();
	$headers[] = 'Content-Type: text/html; charset=UTF-8';
	$headers[] = 'From: ' . esc_html($name) . ' <' . esc_html($fromEmail) . '>';

	wp_mail($to, $subject, $body, $headers);
	delete_option($sender.'_feedback_email');
	delete_option($sender.'_feedback_msg');
    $text = (get_option('qlcd_wp_chatbot_email_sent') != '' ? get_option('qlcd_wp_chatbot_email_sent') : 'Your email was sent successfully. Thanks!');

    $jsonData = '{
        "recipient":{
            "id":"'.$sender.'"
        },
        "message":{
            "text":"'.$text.'"
        }
    }';
    return $jsonData;
}


function qcpd_wpfb_phonenumber_1($sender){
    update_option($sender.'_phone', 1);

    $texts = unserialize(get_option('qlcd_wp_chatbot_asking_phone'));

    $jsonData = '{
        "recipient":{
            "id":"'.$sender.'"
        },
        "message":{
            "text":"'.$texts[array_rand($texts)].'"
        }
    }';
    return $jsonData;
}
function qcpd_wpfb_phonenumber_1_woo($sender){
    update_option($sender.'_phone', 1);

    $texts = unserialize(get_option('qlcd_woo_chatbot_asking_phone'));

    $jsonData = '{
        "recipient":{
            "id":"'.$sender.'"
        },
        "message":{
            "text":"'.$texts[array_rand($texts)].'"
        }
    }';
    return $jsonData;
}

function qcpd_wpfb_phonenumber_2($sender){
    delete_option($sender.'_phone');
    $text = (get_option('qlcd_wp_chatbot_phone_sent') != '' ? get_option('qlcd_wp_chatbot_phone_sent') : 'Thanks for your phone number. We will call you ASAP!');
    $jsonData = '{
        "recipient":{
            "id":"'.$sender.'"
        },
        "message":{
            "text":"'.$text.'"
        }
    }';
    return $jsonData;
}
function qcpd_wpfb_phonenumber_2_woo($sender){
    delete_option($sender.'_phone');
    $text = (get_option('qlcd_woo_chatbot_phone_sent') != '' ? get_option('qlcd_woo_chatbot_phone_sent') : 'Thanks for your phone number. We will call you ASAP!');
    $jsonData = '{
        "recipient":{
            "id":"'.$sender.'"
        },
        "message":{
            "text":"'.$text.'"
        }
    }';
    return $jsonData;
}

function qcpd_wpfb_email_subscription_1($sender, $access_token){
    
    update_option($sender.'_subscription', 1);
    $texts = unserialize(get_option('qlcd_wp_chatbot_asking_email'));
	$userinfo = qcpd_wpfb_userinfo($sender, $access_token);
    $jsonData = '{
        "recipient":{
            "id":"'.$sender.'"
        },
        "message":{
            "text":"Hello '.$userinfo->last_name.', '.$texts[array_rand($texts)].'"
        }
    }';
    return $jsonData;
}




function qcld_wbfb_chatbot_email_subscription($name, $email) {
	
	global $wpdb;
	$table    = $wpdb->prefix.'wpbot_subscription';
	
	$name = $name;
	$email = $email;
	$url = '';
	$user_agent = '';
	
	$response = array();
	$response['status'] = 'fail';
	
	$query = $wpdb->prepare( 
	  "select * from $table where 1 and email = %s", 
	  $email
	);
	
	$email_exists = $wpdb->get_row($query);
	if(empty($email_exists)){
	
		$wpdb->query( $wpdb->prepare( " INSERT INTO $table ( date, name, email, url, user_agent ) VALUES ( %s, %s, %s, %s, %s ) ", array( date('Y-m-d H:i:s'), $name, $email, $url, $user_agent ) ) );
		
		$response['status'] = 'success';
		
		$texts = unserialize(get_option('qlcd_wp_email_subscription_success'));
		$response['msg'] = $texts[array_rand($texts)];
	
	}else{
		$texts = unserialize(get_option('qlcd_wp_email_already_subscribe'));
		$response['msg'] = $texts[array_rand($texts)];
	}
	
	return $response;
}

/*=====Common functions===========*/

/* WPBot MCA white label Addon check */
function qcld_woowbot_mca_is_active_white_label(){
	
	if(function_exists('qcpd_wpwl_white_label_dependencies')){
		return true;
	}else{
		return false;
	}

}

/* WPBot MCA white label Addon check */
function qcld_wpbot_mca_is_active_white_label(){
	
	if(function_exists('qcpd_wpwl_white_label_dependencies')){
		return true;
	}else{
		return false;
	}
	
}


function mca_wpbot_text(){

    if(qcld_wpbot_mca_is_active_white_label() && get_option('wpwl_word_wpbot')!=''){
        return get_option('wpwl_word_wpbot');
    }else{
        return 'WPBot';
    }

}

function mca_woowbot_text(){

    if(qcld_woowbot_mca_is_active_white_label() && get_option('wpwo_word_wpbot')!=''){
        return get_option('wpwo_word_wpbot');
    }else{
        return 'WoowBot';
    }

}

function qcpdmca_is_woowbot_active(){
	
	if(class_exists('QCLD_Woo_Chatbot')){
		return true;
	}else{
		return false;
	}

}

function qcpdmca_is_wpbot_active(){
	
	if(class_exists('qcld_wb_Chatbot')){
		return true;
	}else{
		return false;
	}
	
}


/* Send request for Get Started Button */
function qcpd_wpfb_get_started_button($access_token){
	$jsonData = '{ 
	  "get_started":{
		"payload":"qc-first-handshake"
	  }
	}';
	$url = 'https://graph.facebook.com/v2.6/me/messenger_profile?access_token='.$access_token;
	$jsonDataEncoded = $jsonData;

	$result = wp_remote_post($url, array(
		'headers'   => array(
						'Content-Type' => 'application/json; charset=utf-8'
					),
		'body'      => $jsonDataEncoded,
		'method'    => 'POST'
	));
}

/* Send request for Pass Thread control */
function qcpd_wpfb_pass_thread_control($sender, $access_token){
	$jsonData = '{
		"recipient":{
			"id":"'.$sender.'"
		},
		"target_app_id":"263902037430900"
	}';
	qcpd_wpfb_send_fb_reply($jsonData, $access_token);
	$url = 'https://graph.facebook.com/v2.6/me/pass_thread_control?access_token='.$access_token;
	$jsonDataEncoded = $jsonData;
	
	$result = wp_remote_post($url, array(
		'headers'   => array(
						'Content-Type' => 'application/json; charset=utf-8'
					),
		'body'      => $jsonDataEncoded,
		'method'    => 'POST'
	));
}
add_action('init', 'qcpd_wpfb_get_started_button_setup');
function qcpd_wpfb_get_started_button_setup(){
	
	$access_token = get_option('wpfb_page_access_token');
	if(get_option('wpfb_enable_fbbot')!='on'){
		return;
	}
	if(!get_option('qc_get_started_new') || get_option('qc_get_started_new')!='active'){
		ob_start();
		qcpd_wpfb_get_started_button($access_token);
		$data = ob_get_clean();
		$data = json_decode($data, true);
		if(isset($data['result']) && $data['result']=='success'){
			add_option('qc_get_started_new', 'active');
		}
	}

}

function qc_df_v2_api($query){
	
	$session_id = 'asd2342sde';
    $language = get_option('qlcd_wp_chatbot_dialogflow_agent_language');
    //project ID
    $project_ID = get_option('qlcd_wp_chatbot_dialogflow_project_id');
    // Service Account Key json file
    $JsonFileContents = get_option('qlcd_wp_chatbot_dialogflow_project_key');
    if($project_ID==''){
        echo json_encode(array('error'=>'Project ID is empty'));exit;
    }
    if($JsonFileContents==''){
        echo json_encode(array('error'=>'Key is empty'));exit;
    }
    if( $query==''){
        echo json_encode(array('error'=>'Query text is not added!'));exit;
    }
    $query = sanitize_text_field($query);
    if(isset($_POST['sessionid']) && $_POST['sessionid']!=''){
        $session_id = sanitize_text_field($_POST['sessionid']);
    }
    

    if(file_exists(QCLD_wpCHATBOT_GC_DIRNAME.'/autoload.php')){

        require(QCLD_wpCHATBOT_GC_DIRNAME.'/autoload.php');

        $client = new \Google_Client();
        $client->useApplicationDefaultCredentials();
        $client->setScopes (['https://www.googleapis.com/auth/dialogflow']);
        // Convert to array 
        $array = json_decode($JsonFileContents, true);
        $client->setAuthConfig($array);

        try {
            $httpClient = $client->authorize();
            $apiUrl = "https://dialogflow.googleapis.com/v2/projects/{$project_ID}/agent/sessions/{$session_id}:detectIntent";

            $response = $httpClient->request('POST', $apiUrl, [
                'json' => ['queryInput' => ['text' => ['text' => $query, 'languageCode' => $language]],
                    'queryParams' => ['timeZone' => '']]
            ]);
            
            $contents = $response->getBody()->getContents();
            return $contents;

        }catch(Exception $e) {
            return json_encode(array('error'=>$e->getMessage()));
        }

    }else{
        return json_encode(array('error'=>'API client not found'));
    }
	
}

function qcwpbot_send_response($postfields, $url){
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIfYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/75.0.3770.142 Safari/537.36");
	$res = curl_exec($ch);
	curl_close($ch);
	return $res;
}

function qcwpbot_delete_response($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
	curl_setopt($ch, CURLOPT_SSL_VERIfYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/75.0.3770.142 Safari/537.36");
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $result;
}

/*broadcast a message using send api*/
function qcpd_wpfb_fb_send_api($jsonData, $access_token){
	
	$url = 'https://graph.facebook.com/v5.0/me/messages?access_token='.$access_token;
	$ch = curl_init($url);
	$jsonDataEncoded = $jsonData;
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
	$result = curl_exec($ch);
}

function qcwp_get_fbpost_content($postid, $access_token){
	
	$ch = curl_init();
	//curl_setopt($ch, CURLOPT_SSL_VERIfYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

	curl_setopt($ch, CURLOPT_URL, "https://graph.facebook.com/v3.3/$postid?access_token=$access_token");
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/75.0.3770.142 Safari/537.36");
	$res = curl_exec($ch);
	curl_close($ch);
	$res = json_decode($res, true);
	
	return $res['message'];
	
}