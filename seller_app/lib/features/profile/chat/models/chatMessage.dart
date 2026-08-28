import 'package:intl/intl.dart';

class ChatMessage {
  String? id;
  late int fromId;
  late int toId;
  late String body;
  Map<String, dynamic>? attachment;
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
  
      // Parse the date string into a DateTime object
      DateTime dateTime = DateTime.parse(json['created_at']);
     
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
    attachment = json['attachment'];
    seen = json['seen'] ?? 0;
    createdAt = createdAt;
    updatedAt = updatedAt;
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data =  Map<String, dynamic>();
    data['id'] = id;
    data['from_id'] = fromId;
    data['to_id'] = toId;
    data['body'] = body;
    data['attachment'] = attachment;
    data['seen'] = seen;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    return data;
  }
}
