<?php

namespace App\Http\Controllers\Admin;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\TicketType;
use App\Models\User;
use App\Models\UserFcm;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SplFileInfo;
use Validator;
use App\Traits\HandlesValidation;
use App\Services\MediaService;
use App\Services\SettingService;
use App\Services\FirebaseNotificationService;
class TicketController extends Controller
{
    use HandlesValidation;
    public function index()
    {
        return view('admin.pages.forms.ticket_types');
    }

    public function store(Request $request)
    {
        $rules = [
            'title' => 'required',
        ];

        if ($response = $this->HandlesValidation($request, $rules)) {
            return $response;
        }

        $ticket_data['title'] = $request->title ?? "";

        TicketType::create($ticket_data);

        if ($request->ajax()) {
            return response()->json([
                'message' => labels('admin_labels.ticket_type_added_successfully', 'Ticket Type added successfully')
            ]);
        }
    }

    public function list()
    {
        $search = trim(request('search'));
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $offset = $search || (request('pagination_offset')) ? (request('pagination_offset')) : 0;
        $limit = (request('limit')) ? request('limit') : "10";
        $faqs = TicketType::when($search, function ($query) use ($search) {
            return $query->where('title', 'like', '%' . $search . '%');
        });

        $total = $faqs->count();
        $faqs = $faqs->orderBy(
            $sort == 'date_created' ? 'created_at' : $sort,
            $order
        )
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(function ($f) {

                $edit_url = route('ticket_types.edit', $f->id);
                $delete_url = route('ticket_types.destroy', $f->id);
                $action = '<div class="dropdown bootstrap-table-dropdown">
                <a href="#" class="text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                   <i class="bx bx-dots-horizontal-rounded"></i>
                </a>
                <div class="dropdown-menu table_dropdown ticket_action_dropdown" aria-labelledby="dropdownMenuButton">
                <a class="dropdown-item edit-ticket-type dropdown_menu_items" data-id="' . $f->id . '"><i class="bx bx-pencil mx-2"></i> Edit</a>
                    <a class="dropdown-item delete-data dropdown_menu_items" data-url="' . $delete_url . '"><i class="bx bx-trash mx-2"></i> Delete</a>
                </div>
            </div>';

                return [
                    'id' => $f->id,
                    'title' => $f->title,
                    'date_created' => Carbon::parse($f->created_at)->format('d-m-Y'),
                    'operate' => $action
                ];
            });

        return response()->json([
            "rows" => $faqs,
            "total" => $total,
        ]);
    }

    public function destroy($id)
    {
        $ticket = TicketType::find($id);

        if ($ticket) {
            $ticket->delete();
            return response()->json([
                'error' => false,
                'message' => labels('admin_labels.ticket_type_deleted_successfully', 'Ticket Type deleted successfully!')
            ]);
        } else {
            return response()->json(['error' => labels('admin_labels.data_not_found', 'Data Not Found')]);
        }
    }

    public function edit($id)
    {
        $ticket = TicketType::find($id);

        if (!$ticket) {
            return response()->json(['error' => true, 'message' => labels('admin_labels.data_not_found', 'Data Not Found')], 404);
        }

        return response()->json($ticket);
    }

    public function update(Request $request, $id)
    {

        $ticket = TicketType::find($id);
        if (!$ticket) {
            return response()->json(['error' => true, 'message' => labels('admin_labels.data_not_found', 'Data Not Found')], 404);
        } else {
            $rules = [
                'title' => 'required',
            ];

            if ($response = $this->HandlesValidation($request, $rules)) {
                return $response;
            }

            $ticket->title = $request->input('title');

            $ticket->save();

            if ($request->ajax()) {
                return response()->json([
                    'message' => labels('admin_labels.ticket_type_updated_successfully', 'Ticket Type updated successfully')
                ]);
            }
        }
    }

    public function getTickets($ticketId = null, $ticketTypeId = null, $userId = null, $status = null, $search = null, $offset = 0, $limit = 25, $sort = 'id', $order = 'DESC')
    {
        $query = Ticket::with(['ticketType', 'user']);

        // Apply filters
        if (!empty($ticketId)) {
            $query->where('id', $ticketId);
        }

        if (!empty($ticketTypeId)) {
            $query->where('ticket_type_id', $ticketTypeId);
        }

        if (!empty($userId)) {
            $query->where('user_id', $userId);
        }

        if (!empty($status)) {
            $query->where('status', $status);
        }

        // Search across related fields
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhere('description', 'like', "%$search%")
                    ->orWhereHas('ticketType', function ($q2) use ($search) {
                        $q2->where('title', 'like', "%$search%");
                    })
                    ->orWhereHas('user', function ($q3) use ($search) {
                        $q3->where('id', 'like', "%$search%")
                            ->orWhere('username', 'like', "%$search%")
                            ->orWhere('email', 'like', "%$search%")
                            ->orWhere('mobile', 'like', "%$search%");
                    });
            });
        }

        $total = $query->count();

        $tickets = $query->orderBy($sort, $order)
            ->skip($offset)
            ->take($limit)
            ->get();

        $bulkData = [
            'error' => $tickets->isEmpty(),
            'message' => $tickets->isEmpty()
                ? labels('admin_labels.ticket_not_exist', 'Ticket(s) does not exist')
                : labels('admin_labels.tickets_retrieved_successfully', 'Tickets retrieved successfully'),
            'total' => $total,
            'data' => $tickets->map(function ($ticket) {
                return [
                    'id' => $ticket->id,
                    'user_id' => $ticket->user_id,
                    'ticket_type_id' => $ticket->ticket_type_id,
                    'ticket_type' => $ticket->ticketType->title ?? null,
                    'name' => $ticket->user->username ?? null,
                    'subject' => $ticket->subject,
                    'email' => $ticket->email,
                    'description' => $ticket->description,
                    'status' => $ticket->status,
                    'created_at' => $ticket->created_at,
                    'updated_at' => $ticket->updated_at,
                ];
            }),
        ];

        return $bulkData;
    }

    public function getMessages()
    {
        $ticketId = request()->input('ticket_id', '');
        $userId = request()->input('user_id', '');
        $search = trim(request()->input('search', ''));
        $offset = request()->input('offset', 0);
        $limit = request()->input('limit', 10);
        $sort = request()->input('sort', 'id');
        $order = request()->input('order', 'DESC');
        $msgId = request()->input('msg_id', '');

        $data = config('eshop_pro.type');

        $query = TicketMessage::with(['ticket', 'user']);

        // Apply filters
        if (!empty($ticketId)) {
            $query->where('ticket_id', $ticketId);
        }

        if (!empty($userId)) {
            $query->where('user_id', $userId);
        }

        if (!empty($msgId)) {
            $query->where('id', $msgId);
        }

        // Search
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($uq) use ($search) {
                    $uq->where('id', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");
                })
                    ->orWhereHas('ticket', function ($tq) use ($search) {
                        $tq->where('subject', 'like', "%{$search}%");
                    })
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $total = $query->count();

        $messages = $query->orderBy($sort, $order)
            ->skip($offset)
            ->take($limit)
            ->get();

        $formattedMessages = $messages->map(function ($msg) use ($data) {
            $attachments = [];

            if (!empty($msg->attachments) && $msg->attachments !== 'null') {
                $decoded = json_decode($msg->attachments, true);

                foreach ($decoded as $filePath) {
                    $ext = (new SplFileInfo($filePath))->getExtension();
                    $type = 'other';

                    if (in_array($ext, $data['image']['types'])) {
                        $type = 'image';
                    } elseif (in_array($ext, $data['video']['types'])) {
                        $type = 'video';
                    } elseif (in_array($ext, $data['document']['types'])) {
                        $type = 'document';
                    } elseif (in_array($ext, $data['archive']['types'])) {
                        $type = 'archive';
                    }

                    $attachments[] = [
                        'media' => app(MediaService::class)->getMediaImageUrl($filePath),
                        'type' => $type,
                    ];
                }
            }

            return [
                'id' => $msg->id,
                'user_type' => $msg->user_type,
                'user_id' => $msg->user_id,
                'ticket_id' => $msg->ticket_id,
                'message' => $msg->message ?? '',
                'name' => $msg->user->username ?? '',
                'attachments' => $attachments,
                'subject' => $msg->ticket->subject ?? '',
                'created_at' => Carbon::parse($msg->created_at)->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::parse($msg->updated_at)->format('Y-m-d H:i:s'),
            ];
        });

        return [
            'error' => $formattedMessages->isEmpty(),
            'message' => $formattedMessages->isEmpty()
                ? labels('admin_labels.ticket_messages_not_exist', 'Ticket Message(s) does not exist')
                : labels('admin_labels.messages_retrieved_successfully', 'Message(s) retrieved successfully'),
            'total' => $total,
            'data' => $formattedMessages,
        ];
    }


    public function viewTickets()
    {
        return view('admin.pages.tables.manage_tickets');
    }

    public function getTicketList()
    {
        $search = trim(request()->input('search'));
        $offset = request()->input('pagination_offset', 0);
        $limit = request()->input('limit', 10);
        $sort = request()->input('sort', 'id');
        $order = request()->input('order', 'ASC');

        $query = Ticket::with(['user', 'ticketType']);

        // Search filter
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('ticketType', function ($q2) use ($search) {
                        $q2->where('title', 'like', "%{$search}%");
                    })
                    ->orWhereHas('user', function ($q3) use ($search) {
                        $q3->where('id', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('mobile', 'like', "%{$search}%");
                    });
            });
        }

        $total = $query->count();

        $tickets = $query->orderBy($sort, $order)
            ->skip($offset)
            ->take($limit)
            ->get();

        $rows = $tickets->map(function ($ticket) {
            $deleteUrl = route('tickets.destroy', $ticket->id);

            $operate = '<div class="dropdown bootstrap-table-dropdown">
                <a href="#" class="text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="bx bx-dots-horizontal-rounded"></i>
                </a>
                <div class="dropdown-menu table_dropdown offer_action_dropdown" aria-labelledby="dropdownMenuButton">
                    <a class="dropdown-item dropdown_menu_items view_ticket" data-id="' . $ticket->id . '" data-username="' . $ticket->user->username . '" data-date_created="' . $ticket->created_at . '" data-subject="' . $ticket->subject . '" data-status="' . $ticket->status . '" data-ticket_type="' . (isset($ticket->ticketType->title) && !empty($ticket->ticketType->title) ? $ticket->ticketType->title : '') . '" title="View" data-bs-target="#ticket_modal" data-bs-toggle="modal"><i class="bx bx-pencil mx-2"></i> Edit</a>
                    <a class="dropdown-item delete-data dropdown_menu_items" id="delete-ticket" data-url="' . $deleteUrl . '" data-id="' . $ticket->id . '"><i class="bx bx-trash mx-2"></i> Delete</a>
                </div>
            </div>';

            $statusLabel = match ((string) $ticket->status) {
                '1' => '<label class="badge bg-secondary">PENDING</label>',
                '2' => '<label class="badge bg-info">OPENED</label>',
                '3' => '<label class="badge bg-success">RESOLVED</label>',
                '4' => '<label class="badge bg-danger">CLOSED</label>',
                '5' => '<label class="badge bg-warning">REOPENED</label>',
                default => '',
            };

            return [
                'id' => $ticket->id,
                'ticket_type_id' => $ticket->ticket_type_id,
                'user_id' => $ticket->user_id,
                'subject' => $ticket->subject,
                'email' => $ticket->email,
                'description' => $ticket->description,
                'status' => $statusLabel,
                'last_updated' => $ticket->updated_at,
                'date_created' => Carbon::parse($ticket->created_at)->format('d-m-Y'),
                'username' => $ticket->user->username ?? '',
                'ticket_type' => $ticket->ticketType->title ?? '',
                'operate' => $operate,
            ];
        });

        return response()->json([
            'total' => $total,
            'rows' => $rows,
        ]);
    }

    public function tickets_destroy($id)
    {
        $ticket = Ticket::find($id);

        if ($ticket) {
            $ticket->delete();
            return response()->json([
                'error' => false,
                'message' => labels('admin_labels.ticket_deleted_successfully', 'Ticket deleted successfully!')
            ]);
        } else {
            return response()->json(['error' => labels('admin_labels.data_not_found', 'Data Not Found')]);
        }
    }
    public function sendMessage(Request $request)
    {
        $rules = [
            'ticket_id' => 'required|numeric',
        ];

        // Dynamically add conditional validation for 'message'
        if (empty($request->input('message')) && empty($request->input('attachments'))) {
            $rules['message'] = 'required_without:attachments';
        } else {
            $rules['message'] = 'nullable';
        }

        if ($response = $this->HandlesValidation($request, $rules)) {
            return $response;
        }



        $user_id = auth()->id();
        $ticket_id = $request->input('ticket_id');
        $message = $request->input('message');
        $attachments = $request->input('attachments');

        $user = User::find($user_id);

        if (!$user) {
            return response()->json([
                'error' => true,
                'message' =>
                labels('admin_labels.user_not_found', 'User not found!'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_token(),
                'data' => []
            ]);
        }

        $ticket_messages = new TicketMessage([
            'user_type' => 'admin',
            'user_id' => $user_id,
            'ticket_id' => $ticket_id,
            'message' => $message,
            'attachments' => json_encode($attachments),
        ]);

        $response = $ticket_messages->save();
        $last_insert_id = $ticket_messages->id;


        if ($response) {

            //send notification to user

            if (isset($message) && !empty($message)) {

                $ticket = Ticket::find($ticket_id);

                $fcm_ids = array();
                $customer_result = UserFcm::with('user:id,id,is_notification_on')
                    ->where('user_id', $ticket->user_id)
                    ->whereHas('user', function ($q) {
                        $q->where('is_notification_on', 1);
                    })
                    ->get()
                    ->map(function ($fcm) {
                        return [
                            'fcm_id' => $fcm->fcm_id,
                            'is_notification_on' => $fcm->user?->is_notification_on,
                        ];
                    });

                foreach ($customer_result as $result) {
                    $fcm_ids[] = $result['fcm_id'];
                }

                $fcmMsg = array(
                    'title' => "Support Ticket Message",
                    'body' => (string) $message,
                    'type' => "ticket_message",
                    'ticket_id' => $ticket_id,
                );

                $registrationIDs_chunks = array_chunk($fcm_ids, 1000);
                app(FirebaseNotificationService::class)->sendNotification('', $registrationIDs_chunks, $fcmMsg);
            }

            $type = config('eshop_pro.type');
            $result = $this->getMessages();

            return response()->json([
                'error' => false,
                'message' =>
                labels('admin_labels.ticket_message_sent_successfully', 'Ticket message sent successfully'),
                'data' => $result['data'][0]
            ]);
        } else {
            return response()->json([
                'error' => true,
                'message' =>
                labels('admin_labels.ticket_message_not_sent', 'Ticket message could not be sent!'),
                'data' => []
            ]);
        }
    }

    public function editTicketStatus(Request $request)
    {

        $rules = [
            'ticket_id' => 'required|numeric',
            'status' => 'required',
        ];

        if ($response = $this->HandlesValidation($request, $rules)) {
            return $response;
        }
        $status = $request->input('status');
        $ticket_id = $request->input('ticket_id');

        $ticket = Ticket::find($ticket_id);

        if (!$ticket) {
            return response()->json(['error' => true, 'message' => labels('admin_labels.data_not_found', 'Data Not Found'), 'data' => []]);
        }

        // Update ticket status
        $ticket->status = $status;
        $ticket->save();

        // Additional logic for notifications...

        //send notification to user

        $customer_id = $ticket->user_id;
        $settings = app(SettingService::class)->getSettings('system_settings', true);
        $settings = json_decode($settings, true);
        $app_name = isset($settings['app_name']) && !empty($settings['app_name']) ? $settings['app_name'] : '';
        $customer_res = fetchDetails('users', ['id' => $customer_id], ['username', 'fcm_id']);
        $fcm_ids = array();

        $custom_notification = fetchDetails('custom_messages', ['type' => "ticket_status"], '*');
        $customer_res[0]->username = isset($customer_res[0]->username) ? $customer_res[0]->username : '';

        $hashtag_application_name = '< application_name >';
        $string = isset($custom_notification) && !empty($custom_notification) ? json_encode($custom_notification[0]->message, JSON_UNESCAPED_UNICODE) : '';
        $hashtag = html_entity_decode($string);
        $data1 = str_replace(array($hashtag_application_name), array($app_name), $hashtag);
        $message = outputEscaping(trim($data1, '"'));

        $customer_msg = (!empty($custom_notification)) ? $message : 'Your Support Ticket Status has been updated please noted it. Regards ' . $app_name . '';


        $customer_result = UserFcm::with('user:id,id,is_notification_on')
            ->where('user_id', $customer_id)
            ->whereHas('user', function ($q) {
                $q->where('is_notification_on', 1);
            })
            ->get()
            ->map(function ($fcm) {
                return [
                    'fcm_id' => $fcm->fcm_id,
                    'is_notification_on' => $fcm->user?->is_notification_on,
                ];
            });

        foreach ($customer_result as $result) {
            $fcm_ids[] = $result['fcm_id'];
        }

        $title = (!empty($custom_notification)) ? $custom_notification[0]->title : "Order status updated";
        $fcmMsg = array(
            'title' => (string) $title,
            'body' => (string) $customer_msg,
            'type' => "ticket_status",
            'ticket_id' => $ticket_id,
        );

        $registrationIDs_chunks = array_chunk($fcm_ids, 1000);
        app(FirebaseNotificationService::class)->sendNotification('', $registrationIDs_chunks, $fcmMsg);

        return response()->json([
            'error' => false,
            'message' =>
            labels('admin_labels.ticket_updated_successfully', 'Ticket updated successfully'),
            'data' => $ticket
        ]);
    }
    public function delete_selected_data(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:ticket_types,id'
        ]);

        foreach ($request->ids as $id) {
            $ticket_type = TicketType::find($id);

            if ($ticket_type) {
                TicketType::where('id', $id)->delete();
            }
        }
        TicketType::destroy($request->ids);

        return response()->json(['message' => 'Selected data deleted successfully.']);
    }
    public function delete_selected_ticket_data(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:tickets,id'
        ]);

        foreach ($request->ids as $id) {
            $tickets = Ticket::find($id);

            if ($tickets) {
                Ticket::where('id', $id)->delete();
            }
        }
        Ticket::destroy($request->ids);

        return response()->json(['message' => 'Selected data deleted successfully.']);
    }
}
