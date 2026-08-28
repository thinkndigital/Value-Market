import 'package:intl/intl.dart';

class FAQ {
  late String id;
  String? userId;
  String? sellerId;
  String? productId;
  String? votes;
  late String question;
  late String answer;
  String? answeredBy;
  String? createdAt;
  String? updatedAt;

  FAQ(
      {required this.id,
      this.userId,
      this.sellerId,
      this.productId,
      this.votes,
      required this.question,
      required this.answer,
      this.answeredBy,
      this.createdAt,
      this.updatedAt});

  FAQ.fromJson(Map<String, dynamic> json) {
    if (json['created_at'] != null) {
      // Define the input format
      DateFormat inputFormat = DateFormat('yyyy-MM-dd');
      // Parse the date string into a DateTime object
      DateTime dateTime = inputFormat.parse(json['created_at']);
      // Define the output format
      DateFormat outputFormat = DateFormat('MMMM dd,yyyy');
      createdAt = outputFormat.format(dateTime);
      updatedAt = json['updated_at'];
    }
    id = json['id'].toString();
    userId = json['user_id'].toString();
    sellerId = json['seller_id'].toString();
    productId = json['product_id'].toString();
    votes = json['votes'].toString();
    question = json['question'];
    answer = json['answer'];
    answeredBy = json['answered_by'];
  }
}
