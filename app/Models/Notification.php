<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'data',
        'is_read',
        'data_id',
        'message',
        'notification_for',
    ];

    public function toHtml()
    {
        $data       = json_decode($this->data);
        $link       = '#';
        $icon       = 'fa fa-bell';
        $icon_color = 'bg-primary';
        $text       = '';
        
        if(isset($data->updated_by) && !empty($data->updated_by))
        {
            $usr = User::find($data->updated_by);
        }

        if(!empty($usr))
        {
            // For Deals Notification
            if($this->type == 'assign_lead')
            {
                $not_type = 'simple';
                $not_id= $this->id;
                $link       = route('leads.show', [$data->data_id,]);
                $text       = $usr->name . " " . __('Added you') . " " . __('in lead') . " <b class='font-weight-bold'>" . $data->name . "</b> ";
                $icon       = "fa fa-plus";
                $icon_color = 'bg-primary';
            }
            if($this->type == 'create_deal')
            {
                $not_type = 'simple';
                $not_id= $this->id;
                $link       = route('leads.show', [$data->data_id,]);
                $text       = $usr->name . " " . __('Create deal') . " " . __('from lead') . " <b class='font-weight-bold'>" . $data->name . "</b> ";
                $icon       = "fa fa-plus";
                $icon_color = 'bg-primary';
            }
            if($this->type == 'assign_deal')
            {
                $not_type = 'simple';
                $not_id= $this->id;
                $link       = route('deals.show', [$data->data_id,]);
                $text       = $usr->name . " " . __('Added you') . " " . __('in deal') . " <b class='font-weight-bold'>" . $data->name . "</b> ";
                $icon       = "fa fa-plus";
                $icon_color = 'bg-primary';
            }

            if($this->type == 'create_deal_call')
            {
                $not_type = 'simple';
                $not_id= $this->id;
                $link       = route('deals.show', [$data->data_id,]);
                $text       = $usr->name . " " . __('Create new Deal Call') . " " . __('in deal') . " <b class='font-weight-bold'>" . $data->name . "</b> ";
                $icon       = "fa fa-phone";
                $icon_color = 'bg-info';
            }

            if($this->type == 'update_deal_source')
            {
                $not_type = 'simple';
                $not_id= $this->id;
                $link       = route('deals.show', [$data->data_id,]);
                $text       = $usr->name . " " . __('Update Sources') . " " . __('in deal') . " <b class='font-weight-bold'>" . $data->name . "</b> ";
                $icon       = "fa fa-file-alt";
                $icon_color = 'bg-warning';
            }

            if($this->type == 'create_task')
            {
                $not_type = 'simple';
                $not_id= $this->id;
                $link       = route('deals.show', [$data->data_id,]);
                $text       = $usr->name . " " . __('Create new Task') . " " . __('in deal') . " <b class='font-weight-bold'>" . $data->name . "</b> ";
                $icon       = "fa fa-tasks";
                $icon_color = 'bg-primary';
            }
            if($this->type == 'create_transfer')
            {
                $not_type = 'simple';
                $not_id= $this->id;
                $link       = route('bank-transfer.index');
                $text       = $usr->name . " " . __('Created a new Bank Transfer') .  " <b class='font-weight-bold'>" . $data->name . "</b> ";
                $icon       = "fa fa-tasks";
                $icon_color = 'bg-primary';
            }
            if($this->type == 'create_employee')
            {
                $not_type = 'simple';
                $not_id= $this->id;
                $link       = route('employee.index');
                $text       = $usr->name . " " . __('Created a new Employee') .  " <b class='font-weight-bold'>" . $data->name . "</b> ";
                $icon       = "fa fa-tasks";
                $icon_color = 'bg-primary';
            }
            if($this->type == 'assign_project')
            {
                $not_type = 'simple';
                $not_id= $this->id;
                $link       = route('projects.show',[$data->data_id]);
                $text       = $usr->name . " " . __('Assigned a Project') .  " <b class='font-weight-bold'>" . $data->name . "</b> ";
                $icon       = "fa fa-tasks";
                $icon_color = 'bg-primary';
            }

            if($this->type == 'add_product')
            {
                $not_type = 'simple';
                $not_id= $this->id;
                $link       = route('deals.show', [$data->data_id,]);
                $text       = $usr->name . " " . __('Add new Products') . " " . __('in deal') . " <b class='font-weight-bold'>" . $data->name . "</b> ";
                $icon       = "fa fa-dolly";
                $icon_color = 'bg-danger';
            }

            if($this->type == 'add_discussion')
            {
                $not_type = 'simple';
                $not_id= $this->id;
                $link       = route('deals.show', [$data->deal_id,]);
                $text       = $usr->name . " " . __('Add new Discussion') . " " . __('in deal') . " <b class='font-weight-bold'>" . $data->name . "</b> ";
                $icon       = "fa fa-comments";
                $icon_color = 'bg-info';
            }

            if($this->type == 'move_deal')
            {
                $not_type = 'simple';
                $not_id= $this->id;
                $link       = route('deals.show', [$data->data_id,]);
                $text       = $usr->name . " " . __('Moved the deal') . " <b class='font-weight-bold'>" . $data->name . "</b> " . __('from') . " " . __(ucwords($data->old_status)) . " " . __('to') . " " . __(ucwords($data->new_status));
                $icon       = "fa fa-arrows-alt";
                $icon_color = 'bg-primary';
            }
            // end deals

            // for estimations
            if($this->type == 'assign_estimation')
            {
                $not_type = 'simple';
                $not_id= $this->id;
                $link       = route('estimations.show', [$data->estimation_id,]);
                $text       = $usr->name . " " . __('Added you') . " " . __('in estimation') . " <b class='font-weight-bold'>" . $data->estimation_name . "</b> ";
                $icon       = "fa fa-plus";
                $icon_color = 'bg-primary';
            }
            // end estimations

            // For Leads Notification
            // if($this->type == 'assign_lead')
            // {
            $not_type = 'simple';
            $not_id= $this->id;
            //     $link       = route('leads.show', [$data->lead_id,]);
            //     $text       = $usr->name . " " . __('Added you') . " " . __('in lead') . " <b class='font-weight-bold'>" . $data->name . "</b> ";
            //     $icon       = "fa fa-plus";
            //     $icon_color = 'bg-primary';
            // }

            if($this->type == 'create_lead_call')
            {
                $not_type = 'simple';
                $not_id= $this->id;
                $link       = route('leads.show', [$data->lead_id,]);
                $text       = $usr->name . " " . __('Create new Lead Call') . " " . __('in lead') . " <b class='font-weight-bold'>" . $data->name . "</b> ";
                $icon       = "fa fa-phone";
                $icon_color = 'bg-info';
            }

            if($this->type == 'update_lead_source')
            {
                $not_type = 'simple';
                $not_id= $this->id;
                $link       = route('leads.show', [$data->lead_id,]);
                $text       = $usr->name . " " . __('Update Sources') . " " . __('in lead') . " <b class='font-weight-bold'>" . $data->name . "</b> ";
                $icon       = "fa fa-file-alt";
                $icon_color = 'bg-warning';
            }

            if($this->type == 'add_lead_product')
            {
                $not_type = 'simple';
                $not_id= $this->id;
                $link       = route('leads.show', [$data->lead_id,]);
                $text       = $usr->name . " " . __('Add new Products') . " " . __('in lead') . " <b class='font-weight-bold'>" . $data->name . "</b> ";
                $icon       = "fa fa-dolly";
                $icon_color = 'bg-danger';
            }

            if($this->type == 'add_lead_discussion')
            {
                $not_type = 'simple';
                $not_id= $this->id;
                $link       = route('leads.show', [$data->lead_id,]);
                $text       = $usr->name . " " . __('Add new Discussion') . " " . __('in lead') . " <b class='font-weight-bold'>" . $data->name . "</b> ";
                $icon       = "fa fa-comments";
                $icon_color = 'bg-info';
            }

            if($this->type == 'move_lead')
            {
                $not_type = 'simple';
                $not_id= $this->id;
                $link       = route('leads.show', [$data->lead_id,]);
                $text       = $usr->name . " " . __('Moved the lead') . " <b class='font-weight-bold'>" . $data->name . "</b> " . __('from') . " " . __(ucwords($data->old_status)) . " " . __('to') . " " . __(ucwords($data->new_status));
                $icon       = "fa fa-arrows-alt";
                $icon_color = 'bg-primary';
            }
            if($this->type == 'leave')
            {
                $not_type = 'approval';
                $link       = route('leave.show', [$data->data_id,]);
                $text       = $usr->name . " " . @$this->notification_for . " <b class='font-weight-bold'>" . $data->name . "</b> ";
                $icon       = "fa fa-eye";
                $icon_color = 'bg-primary';
            }
            if($this->type == 'task')
            {
                $not_type = 'simple';
                $not_id= $this->id;
                $link       = route('projects.tasks.index', [$data->project_id,]);
                $text       = $usr->name . " " . @$this->notification_for . " <b class='font-weight-bold'>" . $data->name . "</b> ";
                $icon       = "fa fa-eye";
                $icon_color = 'bg-primary';
            }
            if($this->type == 'checklist')
            {
                $not_type = 'simple';
                $not_id= $this->id;
                $link       = route('projects.tasks.index', [$data->project_id]);
                $text       = $usr->name . " " . @$this->notification_for . " <b class='font-weight-bold'>" . $data->name . "</b> ";
                $icon       = "fa fa-eye";
                $icon_color = 'bg-primary';
            }
            if($this->type == 'comment')
            {
                $not_type = 'simple';
                $not_id= $this->id;
                $link       = route('projects.tasks.index', [$data->project_id,]);
                $text       = $usr->name . " " . @$this->notification_for . " <b class='font-weight-bold'>" . $data->name . "</b> ";
                $icon       = "fa fa-eye";
                $icon_color = 'bg-primary';
            }
            if($this->type == 'bug')
            {
                
                $not_type = 'simple';
                $not_id= $this->id;// dd($this->data);
                $link       = route('task.bug.kanban', [@$data->project_id]);
                $text       = $usr->name . " " . @$this->notification_for . " <b class='font-weight-bold'>" . $data->name . "</b> ";
                $icon       = "fa fa-eye";
                $icon_color = 'bg-primary';
            }
            if($this->type == 'termination')
            {
                
                $not_type = 'simple';
                $not_id= $this->id;// dd($this->data);
                $link       = route('termination.show', [@$data->data_id]);
                $text       = $data->name . " " . @$this->notification_for . " <b class='font-weight-bold'>" . $usr->name . "</b> ";
                $icon       = "fa fa-eye";
                $icon_color = 'bg-primary';
            }
            if($this->type == 'warning')
            {
                
                $not_type = 'simple';
                $not_id= $this->id;// dd($this->data);
                $link       = route('warning.show', [@$data->data_id]);
                $text       = $data->name . " " . @$this->notification_for . " <b class='font-weight-bold'>" . $usr->name . "</b> ";
                $icon       = "fa fa-eye";
                $icon_color = 'bg-primary';
            }
            if($this->type == 'resignation')
            {
                
                $not_type = 'simple';
                $not_id= $this->id;// dd($this->data);
                $link       = route('resignation.show', [@$data->data_id]);
                $text       = $data->name . " " . @$this->notification_for ;
                $icon       = "fa fa-eye";
                $icon_color = 'bg-primary';
            }
            if($this->type == 'transfer')
            {
                
                $not_type = 'simple';
                $not_id= $this->id;// dd($this->data);
                $link       = route('transfer.show', [@$data->data_id]);
                $text       = $data->name . " " . @$this->notification_for . " <b class='font-weight-bold'>" . $usr->name . "</b> ";
                $icon       = "fa fa-eye";
                $icon_color = 'bg-primary';
            }
            if($this->type == 'promotion')
            {
                
                $not_type = 'simple';
                $not_id= $this->id;// dd($this->data);
                $link       = route('promotion.show', [@$data->data_id]);
                $text       = $data->name . " " . @$this->notification_for . $data->promotion." <b class='font-weight-bold'>by " . $usr->name . "</b> ";
                $icon       = "fa fa-eye";
                $icon_color = 'bg-primary';
            }
            if($this->type == 'award')
            {
                
                $not_type = 'simple';
                $not_id= $this->id;// dd($this->data);
                $link       = route('award.show', [@$data->data_id]);
                $text       = $data->name . " " . @$this->notification_for ." <b class='font-weight-bold'> " . $usr->name . "</b> ";
                $icon       = "fa fa-eye";
                $icon_color = 'bg-primary';
            }
            if($this->type == 'travel')
            {
                
                $not_type = 'simple';
                $not_id= $this->id;// dd($this->data);
                $link       = route('travel.show', [@$data->data_id]);
                $text       = $data->name . " " . @$this->notification_for;
                $icon       = "fa fa-eye";
                $icon_color = 'bg-primary';
            }
            if($this->type == 'complaint')
            {
                $not_type = 'simple';
                $not_id= $this->id;
                $link       = route('complaint.show', [@$data->data_id]);
                $text       = $data->name . " " . @$this->notification_for . " <b class='font-weight-bold'>" . $usr->name . "</b> ";
                $icon       = "fa fa-eye";
                $icon_color = 'bg-primary';
            }
            if($this->type == 'announcement')
            {
                $not_type = 'simple';
                $not_id= $this->id;
                $link       = route('announcement.index', );
                $text       = @$usr->name . " " . @$this->notification_for ;
                $icon       = "fa fa-eye";
                $icon_color = 'bg-primary';
            }
            if($this->type == 'holiday')
            {
                $not_type = 'simple';
                $not_id= $this->id;
                $link       = route('holiday.index', );
                $text       = @$usr->name . " " . @$this->notification_for ;
                $icon       = "fa fa-eye";
                $icon_color = 'bg-primary';
            }
            if($this->type == 'event')
            {
                $not_type = 'simple';
                $not_id= $this->id;
                $link       = route('event.index', );
                $text       = @$usr->name . " " . @$this->notification_for ;
                $icon       = "fa fa-eye";
                $icon_color = 'bg-primary';
            }
            if($this->type == 'policy')
            {
                $not_type = 'simple';
                $not_id= $this->id;
                $link       = route('company-policy.index', );
                $text       = @$usr->name . " " . @$this->notification_for ;
                $icon       = "fa fa-eye";
                $icon_color = 'bg-primary';
            }
            // end Leads

            $date = $this->created_at->diffForHumans();
            // $html = '<a href="' . $link . '" class="list-group-item list-group-item-action">
            //                     <div class="d-flex align-items-center">
            //                             <div>
            //                                 <span class="avatar ' . $icon_color . ' text-white rounded-circle"><i class="' . $icon . '"></i></span>
            //                             </div>
            //                         <div class="flex-fill ml-3">
            //                             <div class="h6 text-sm mb-0">' . $text . '</div>
            //                             <small class="text-muted text-xs">' . $date . '</small>
            //                         </div>
            //                     </div>
            //                 </a>';
            $html = '<a href="#"
                        class="list-group-item list-group-item-action notification_model"
                        onclick="showNotificationModal(this)"
                        data-link="' . $link . '" data-type="'.$not_type.'" data-notificationId="'.$not_id.'">
                        <div class="d-flex align-items-center p-1 border-bottom">
                            <div>
                                <span class="avatar ' . $icon_color . ' text-white rounded-circle" style="max-width: 40px; max-height: 40px; margin-right: 7px;">
                                    <i class="' . $icon . '"></i>
                                </span>
                            </div>
                            <div class="flex-fill ml-3">
                                <div class="h6 text-sm mb-0 notification-message">' . $text . '</div>
                                <small class="text-muted text-xs">' . $date . '</small>
                            </div>
                        </div>
                    </a>';

        }
        else
        {
            $html = '';
        }

        return $html;
    }
}
