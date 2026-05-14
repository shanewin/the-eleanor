<?php
$pageTitle = 'Settings';
$activePage = 'settings';
$extraJs = ['/admin/js/settings.js'];
include __DIR__ . '/includes/layout-header.php';
if (!isOwner()) { echo '<div class="alert alert-danger">Access denied. Owner only.</div>'; include __DIR__ . '/includes/layout-footer.php'; exit; }
?>

            <h1 class="h3 fw-bold mb-4">Settings</h1>

            <!-- Settings Tab Navigation -->
            <ul class="nav nav-tabs border-secondary mb-4" id="settingsTabs">
                <li class="nav-item">
                    <a class="nav-link active text-white" href="#" data-settings-tab="general" onclick="showSettingsTab('general', event)">
                        <i class="bi bi-gear me-1"></i>General
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white-50" href="#" data-settings-tab="automation" onclick="showSettingsTab('automation', event)">
                        <i class="bi bi-lightning-charge me-1"></i>Automation
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white-50" href="#" data-settings-tab="sms" onclick="showSettingsTab('sms', event)">
                        <i class="bi bi-chat-left-text me-1"></i>SMS
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white-50" href="#" data-settings-tab="ai" onclick="showSettingsTab('ai', event)">
                        <i class="bi bi-robot me-1"></i>AI Messaging
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white-50" href="#" data-settings-tab="integrations" onclick="showSettingsTab('integrations', event)">
                        <i class="bi bi-plug me-1"></i>Integrations
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white-50" href="#" data-settings-tab="jobs" onclick="showSettingsTab('jobs', event)">
                        <i class="bi bi-list-check me-1"></i>Jobs
                        <span id="jobsTabBadge" class="badge bg-danger ms-1" style="display:none;font-size:0.65rem"></span>
                    </a>
                </li>
            </ul>

            <div class="row">
                <div class="col-lg-8">

                    <!-- ═══ General Tab ═══ -->
                    <div class="settings-tab-pane" id="settingsTab-general">
                        <div class="card bg-body-tertiary border-0 mb-4">
                            <div class="card-body p-4">
                                <h5 class="fw-semibold mb-1">Notification Recipients</h5>
                                <p class="text-white-50 small mb-3">Lead notifications, enrichment reports, and tour-scheduled alerts go to every account with the <strong>Owner</strong> role. Manage who that is on the <a href="/admin/brokers.php" class="text-info">Brokers</a> page.</p>
                                <div id="ownerRecipientsList" class="small text-white-50">Loading owners…</div>
                            </div>
                        </div>
                    </div>

                    <!-- ═══ Automation Tab ═══ -->
                    <div class="settings-tab-pane" id="settingsTab-automation" style="display:none">
                        <div class="card bg-body-tertiary border-0 mb-4">
                            <div class="card-body p-4">
                                <h5 class="fw-semibold mb-1">Outbound automation</h5>
                                <p class="text-white-50 small mb-3">Master switches for each channel that fires after a form submission. Turning one off means nothing is sent on that channel — the lead still appears in the dashboard.</p>
                                <hr class="border-secondary my-3">

                                <!-- Welcome SMS -->
                                <div class="d-flex justify-content-between align-items-start py-3 border-bottom border-secondary border-opacity-25">
                                    <div class="me-3">
                                        <div class="fw-semibold text-white mb-1">Welcome SMS to applicants</div>
                                        <div class="text-white-50 small">Telnyx + Claude conversational SMS that follows up after every form submission and can book tours.</div>
                                    </div>
                                    <div class="form-check form-switch mt-1">
                                        <input class="form-check-input" type="checkbox" id="autoSmsEnabled" style="width:3rem;height:1.5rem;cursor:pointer">
                                    </div>
                                </div>

                                <!-- Applicant welcome email -->
                                <div class="d-flex justify-content-between align-items-start py-3 border-bottom border-secondary border-opacity-25">
                                    <div class="me-3">
                                        <div class="fw-semibold text-white mb-1">Welcome email to applicants</div>
                                        <div class="text-white-50 small">Personalized email with a Book a Showing CTA, sent right after submission. Independent of SMS — owners can run either, both, or neither.</div>
                                    </div>
                                    <div class="form-check form-switch mt-1">
                                        <input class="form-check-input" type="checkbox" id="autoApplicantEmailEnabled" style="width:3rem;height:1.5rem;cursor:pointer">
                                    </div>
                                </div>

                                <!-- Enrichment report -->
                                <div class="d-flex justify-content-between align-items-start py-3 border-bottom border-secondary border-opacity-25">
                                    <div class="me-3">
                                        <div class="fw-semibold text-white mb-1">AI enrichment report to owners</div>
                                        <div class="text-white-50 small">Apollo + PDL intelligence report emailed to owners with title, company, behavior, and LinkedIn data on the lead.</div>
                                    </div>
                                    <div class="form-check form-switch mt-1">
                                        <input class="form-check-input" type="checkbox" id="autoEnrichmentEmailEnabled" style="width:3rem;height:1.5rem;cursor:pointer">
                                    </div>
                                </div>

                                <!-- Owner notification -->
                                <div class="d-flex justify-content-between align-items-start py-3">
                                    <div class="me-3">
                                        <div class="fw-semibold text-white mb-1">New lead notification to owners</div>
                                        <div class="text-white-50 small">Basic "you have a new lead" email with the form data. <span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>Off means new leads only show up in the dashboard, not in your inbox.</span></div>
                                    </div>
                                    <div class="form-check form-switch mt-1">
                                        <input class="form-check-input" type="checkbox" id="autoOwnerNotificationEnabled" style="width:3rem;height:1.5rem;cursor:pointer">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card bg-body-tertiary border-0 mb-4">
                            <div class="card-body p-4">
                                <h5 class="fw-semibold mb-1">Email personalization</h5>
                                <p class="text-white-50 small mb-3">Custom guidance for the AI-written opener in the applicant welcome email. Mirrors the SMS welcome instructions.</p>
                                <hr class="border-secondary my-3">

                                <div class="mb-3">
                                    <label class="form-label small text-white-50" for="applicantEmailInstructions">Email opener instructions</label>
                                    <textarea class="form-control form-control-sm bg-dark border-secondary text-white" id="applicantEmailInstructions" rows="3" maxlength="1000"
                                        placeholder="Optional. E.g. 'Mention we're offering a free month on select units' or 'Reference our rooftop terrace for waitlist leads.'"></textarea>
                                    <div class="form-text text-white-50 small">Leave blank for the default warm leasing-agent tone.</div>
                                </div>
                            </div>
                        </div>

                        <div class="card bg-body-tertiary border-0 mb-4">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="fw-semibold mb-1">Unsubscribe list</h5>
                                        <p class="text-white-50 small mb-0">Applicants who clicked the unsubscribe link. They won't receive future welcome emails.</p>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-semibold text-white" style="font-size:1.5rem" id="suppressionCount">—</div>
                                        <div class="text-white-50 small">unsubscribed</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="automationSettingsStatus" class="small mb-3" style="display:none"></div>
                        <button class="btn btn-primary" onclick="saveAutomationSettings()">Save Automation Settings</button>
                    </div>

                    <!-- ═══ SMS Tab ═══ -->
                    <div class="settings-tab-pane" id="settingsTab-sms" style="display:none">
                        <div class="card bg-body-tertiary border-0 mb-4">
                            <div class="card-body p-4">
                                <h5 class="fw-semibold mb-1">SMS Schedule</h5>
                                <p class="text-white-50 small mb-3">When the SMS auto-reply is allowed to send. Master on/off lives in the <a href="#" onclick="showSettingsTab('automation', event)" class="text-info">Automation tab</a>.</p>

                                <div id="smsSettingsBody">
                                    <hr class="border-secondary my-3">

                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <label class="form-label small text-white-50">Send Window Start</label>
                                            <input type="time" class="form-control form-control-sm bg-dark border-secondary text-white" id="smsWindowStart" value="09:00">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small text-white-50">Send Window End</label>
                                            <input type="time" class="form-control form-control-sm bg-dark border-secondary text-white" id="smsWindowEnd" value="19:00">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label small text-white-50">Active Days</label>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <label class="btn btn-sm btn-outline-secondary sms-day-btn"><input type="checkbox" class="d-none sms-day-check" value="0"> Sun</label>
                                            <label class="btn btn-sm btn-outline-secondary sms-day-btn"><input type="checkbox" class="d-none sms-day-check" value="1" checked> Mon</label>
                                            <label class="btn btn-sm btn-outline-secondary sms-day-btn"><input type="checkbox" class="d-none sms-day-check" value="2" checked> Tue</label>
                                            <label class="btn btn-sm btn-outline-secondary sms-day-btn"><input type="checkbox" class="d-none sms-day-check" value="3" checked> Wed</label>
                                            <label class="btn btn-sm btn-outline-secondary sms-day-btn"><input type="checkbox" class="d-none sms-day-check" value="4" checked> Thu</label>
                                            <label class="btn btn-sm btn-outline-secondary sms-day-btn"><input type="checkbox" class="d-none sms-day-check" value="5" checked> Fri</label>
                                            <label class="btn btn-sm btn-outline-secondary sms-day-btn"><input type="checkbox" class="d-none sms-day-check" value="6"> Sat</label>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <label class="form-label small text-white-50">Campaign Start Date <span class="text-white-50">(optional)</span></label>
                                            <input type="date" class="form-control form-control-sm bg-dark border-secondary text-white" id="smsCampaignStart">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small text-white-50">Campaign End Date <span class="text-white-50">(optional)</span></label>
                                            <input type="date" class="form-control form-control-sm bg-dark border-secondary text-white" id="smsCampaignEnd">
                                        </div>
                                    </div>
                                </div>

                                <div id="smsSettingsStatus" class="small mb-3" style="display:none"></div>
                                <button class="btn btn-primary" onclick="saveSMSSettings()">Save SMS Settings</button>
                            </div>
                        </div>
                    </div>

                    <!-- ═══ AI Messaging Tab ═══ -->
                    <div class="settings-tab-pane" id="settingsTab-ai" style="display:none">
                        <div class="card bg-body-tertiary border-0 mb-4">
                            <div class="card-body p-4">
                                <h5 class="fw-semibold mb-1">AI Messaging Behavior</h5>
                                <p class="text-white-50 small mb-3">Customize how the AI leasing agent communicates with leads via SMS.</p>
                                <hr class="border-secondary my-3">

                                <div class="mb-3">
                                    <label class="form-label small text-white-50" for="aiTone">AI Personality</label>
                                    <select class="form-select form-select-sm bg-dark border-secondary text-white" id="aiTone">
                                        <option value="friendly">Friendly and warm</option>
                                        <option value="professional">Professional and polished</option>
                                        <option value="casual">Casual and laid-back</option>
                                        <option value="enthusiastic">Enthusiastic and energetic</option>
                                    </select>
                                    <div class="form-text text-white-50 small">Sets the overall tone for all AI responses.</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small text-white-50" for="aiWelcomeInstructions">Welcome Message Instructions</label>
                                    <textarea class="form-control form-control-sm bg-dark border-secondary text-white" id="aiWelcomeInstructions" rows="3" maxlength="1000"
                                        placeholder="Tell the AI what the first text to a new lead should include. E.g. 'Mention we're offering a free month on select units.'"></textarea>
                                    <div class="form-text text-white-50 small">Leave blank to use the default welcome message.</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small text-white-50" for="aiTourHours">Tour Availability</label>
                                    <textarea class="form-control form-control-sm bg-dark border-secondary text-white" id="aiTourHours" rows="2" maxlength="1000"
                                        placeholder="Weekdays 10am-6pm, Saturdays 11am-4pm"></textarea>
                                    <div class="form-text text-white-50 small">When the AI should suggest tours. Leave blank for default hours.</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small text-white-50" for="aiTalkingPoints">Talking Points &amp; Priorities</label>
                                    <textarea class="form-control form-control-sm bg-dark border-secondary text-white" id="aiTalkingPoints" rows="4" maxlength="1000"
                                        placeholder="Things the AI should emphasize. One per line.&#10;&#10;Example:&#10;We're offering 1 month free on select units&#10;Push for in-person tours&#10;Mention the rooftop terrace"></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small text-white-50" for="aiExtraPropertyInfo">Additional Property Info</label>
                                    <textarea class="form-control form-control-sm bg-dark border-secondary text-white" id="aiExtraPropertyInfo" rows="4" maxlength="1000"
                                        placeholder="Extra details the AI should know. E.g. 'Lobby renovation finishes June 15' or 'We now accept Rhino guarantors.'"></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small text-white-50" for="aiOffLimits">Topics to Avoid</label>
                                    <textarea class="form-control form-control-sm bg-dark border-secondary text-white" id="aiOffLimits" rows="3" maxlength="1000"
                                        placeholder="Things the AI should never say. One per line.&#10;&#10;Example:&#10;Don't mention the construction on 3rd Ave&#10;Never quote exact application fees"></textarea>
                                </div>

                                <div id="aiSettingsStatus" class="small mb-3" style="display:none"></div>
                                <button class="btn btn-primary" onclick="saveAISettings()">Save AI Settings</button>
                            </div>
                        </div>
                    </div>

                    <!-- ═══ Integrations Tab ═══ -->
                    <div class="settings-tab-pane" id="settingsTab-integrations" style="display:none">
                        <div class="card bg-body-tertiary border-0 mb-4">
                            <div class="card-body p-4">
                                <h5 class="fw-semibold mb-1"><i class="bi bi-telephone me-2"></i>Telnyx SMS</h5>
                                <p class="text-white-50 small mb-3">Phone number and messaging profile for SMS communication.</p>
                                <div class="row mb-2">
                                    <div class="col-4"><span class="text-white-50 small">Phone Number</span></div>
                                    <div class="col-8"><span class="text-white small">+1 (718) 675-5803</span></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-4"><span class="text-white-50 small">Profile ID</span></div>
                                    <div class="col-8"><span class="text-white small" style="font-family:monospace;font-size:0.75rem">40019de4-32dd-46c8-a171-3b5fdaf37688</span></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-4"><span class="text-white-50 small">Webhook URL</span></div>
                                    <div class="col-8"><span class="text-white small" style="font-family:monospace;font-size:0.75rem">https://eleanorbk.com/api/telnyx-webhook.php</span></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-4"><span class="text-white-50 small">Status</span></div>
                                    <div class="col-8"><span class="badge bg-success bg-opacity-25 text-success" style="font-size:0.7rem">Active</span></div>
                                </div>
                            </div>
                        </div>

                        <div class="card bg-body-tertiary border-0 mb-4">
                            <div class="card-body p-4">
                                <h5 class="fw-semibold mb-1"><i class="bi bi-database me-2"></i>Supabase</h5>
                                <p class="text-white-50 small mb-3">Database and authentication backend.</p>
                                <div class="row mb-2">
                                    <div class="col-4"><span class="text-white-50 small">Status</span></div>
                                    <div class="col-8"><span class="badge bg-success bg-opacity-25 text-success" style="font-size:0.7rem">Connected</span></div>
                                </div>
                            </div>
                        </div>

                        <div class="card bg-body-tertiary border-0 mb-4">
                            <div class="card-body p-4">
                                <h5 class="fw-semibold mb-1"><i class="bi bi-stars me-2"></i>Lead Enrichment</h5>
                                <p class="text-white-50 small mb-3">Apollo, People Data Labs, LinkedIn, and Tavily integrations for lead enrichment.</p>
                                <div class="row mb-2">
                                    <div class="col-4"><span class="text-white-50 small">Status</span></div>
                                    <div class="col-8"><span class="badge bg-success bg-opacity-25 text-success" style="font-size:0.7rem">Active</span></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ═══ Jobs Tab ═══ -->
                    <div class="settings-tab-pane" id="settingsTab-jobs" style="display:none">

                        <!-- Cron jobs status -->
                        <div class="card bg-body-tertiary border-0 mb-4">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h5 class="fw-semibold mb-1">Cron jobs</h5>
                                        <p class="text-white-50 small mb-0">Scheduled background work. Healthy jobs run on time and finish without errors.</p>
                                    </div>
                                    <button class="btn btn-sm btn-outline-secondary" onclick="loadCronJobs()" title="Refresh">
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </button>
                                </div>
                                <div id="cronJobsBody" class="small">Loading…</div>
                            </div>
                        </div>

                        <!-- Lead-processing jobs -->
                        <div class="card bg-body-tertiary border-0 mb-4">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h5 class="fw-semibold mb-1">Lead-processing jobs</h5>
                                        <p class="text-white-50 small mb-0">Background work after form submissions — notification email, enrichment, welcome SMS. Healthy jobs finish in seconds; this tab surfaces anything that didn't.</p>
                                    </div>
                                    <button class="btn btn-sm btn-outline-secondary" onclick="loadLeadJobs()" title="Refresh">
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </button>
                                </div>

                                <h6 class="text-danger small text-uppercase mt-3 mb-2">Failed</h6>
                                <div id="jobsFailedBody" class="text-white-50 small">Loading…</div>

                                <h6 class="text-warning small text-uppercase mt-4 mb-2">Stuck (running &gt; 5 min)</h6>
                                <div id="jobsStuckBody" class="text-white-50 small">Loading…</div>

                                <h6 class="text-success small text-uppercase mt-4 mb-2">Recent completions</h6>
                                <div id="jobsDoneBody" class="text-white-50 small">Loading…</div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

<?php include __DIR__ . '/includes/layout-footer.php'; ?>
