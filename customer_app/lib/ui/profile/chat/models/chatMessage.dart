import 'package:intl/intl.dart';

class ChatMessage {
  String? id;
  late int fromId;
  late int toId;
  late String body;
  List<ChatAttachment> attachments = [];
  int? seen;
  String? createdAt;
  String? updatedAt;

  ChatMessage(
      {id,
      required fromId,
      required toId,
      required body,
      attachment,
      seen,
      createdAt,
      updatedAt});

  ChatMessage.fromJson(Map<String, dynamic> json) {
    if (json['created_at'] != null) {
      // Define the input format
      // Parse the date string into a DateTime object
      DateTime dateTime = DateTime.parse(json['created_at']);
      // Convert UTC to local time
      DateTime localDateTime = dateTime.toLocal();
      // Define the output format
      DateFormat outputFormat = DateFormat('yyyy-MM-dd kk:mm');
      createdAt = outputFormat.format(localDateTime);
      updatedAt = json['updated_at'];
    }
    id = json['id'];
    fromId = int.parse(json['from_id'].toString());
    toId = int.parse(json['to_id'].toString());
    body = json['body'] ?? json['message'];
    if (json['attachment'] != null) {
      if (json['attachment'] is List) {
        // Normalize if already a list of attachments (unlikely here)
        attachments =
            List<Map<String, dynamic>>.from(json['attachment']).map((att) {
          return ChatAttachment(
            url: att['new_name'] ?? att['file'] ?? '',
            type: _detectAttachmentType(att['new_name'] ?? ''),
          );
        }).toList();
      } else if (json['attachment'] is Map) {
        // Live chat - single attachment
        final att = json['attachment'];
        attachments = [
          if (att['new_name'] != null || att['file'] != null)
            ChatAttachment(
              url: att['new_name'] ?? att['file'] ?? '',
              type: _detectAttachmentType(att['new_name'] ?? ''),
            )
        ];
      }
    } else {
      attachments = [];
    }
    seen = json['seen'];
    createdAt = createdAt;
    updatedAt = updatedAt;
  }
  ChatMessage.fromTicketJson(Map<String, dynamic> json) {
    if (json['created_at'] != null) {
      // Define the input format
      // Parse the date string into a DateTime object
      DateTime dateTime = DateTime.parse(json['created_at']);
      // Convert UTC to local time
      DateTime localDateTime = dateTime.toLocal();
      // Define the output format
      DateFormat outputFormat = DateFormat('yyyy-MM-dd kk:mm');
      createdAt = outputFormat.format(localDateTime);
      updatedAt = json['updated_at'];
    }
    id = json['id'].toString();
    fromId = int.parse(json['user_id'].toString());
    toId = 0;
    body = json['message'] ?? json['message'];
    if (json['attachments'] != null) {
      attachments =
          List<Map<String, dynamic>>.from(json['attachments']).map((att) {
        return ChatAttachment(
          url: att['media'] ?? '',
          type: att['type'] ?? 'other',
        );
      }).toList();
    }
    seen = json['seen'];
    createdAt = createdAt;
    updatedAt = updatedAt;
  }
}

String _detectAttachmentType(String url) {
  if (url.endsWith(".jpg") ||
      url.endsWith(".png") ||
      url.endsWith(".jpeg") ||
      url.endsWith(".webp")) {
    return "image";
  }
  return "other";
}

class ChatAttachment {
  final String url;
  final String type; // "image", "file", etc.

  ChatAttachment({required this.url, required this.type});
}
