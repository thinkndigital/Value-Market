import 'package:intl/intl.dart';

class Ticket {
  int? id;
  int? ticketTypeId;
  int? userId;
  String? subject;
  String? email;
  String? description;
  int? status;
  String? createdAt;
  String? updatedAt;
  String? ticketType;
  String? name;

  Ticket(
      {this.id,
      this.ticketTypeId,
      this.userId,
      this.subject,
      this.email,
      this.description,
      this.status,
      this.createdAt,
      this.updatedAt,
      this.ticketType,
      this.name});

  Ticket.fromJson(Map<String, dynamic> json) {
    // Define the input format
    DateFormat inputFormat = DateFormat('yyyy-MM-dd');
    // Parse the date string into a DateTime object
    DateTime dateTime = inputFormat.parse(json['created_at']);
    // Define the output format
    DateFormat outputFormat = DateFormat('MMM dd, yyyy');

    id = json['id'];
    ticketTypeId = json['ticket_type_id'];
    userId = json['user_id'];
    subject = json['subject'];
    email = json['email'];
    description = json['description'];
    status = json['status'];
    createdAt = outputFormat.format(dateTime);
    updatedAt = json['updated_at'];
    ticketType = json['ticket_type'];
    name = json['name'];
  }
}
