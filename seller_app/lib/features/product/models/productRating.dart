import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:intl/intl.dart';

class ProductRating {
  bool? error;
  String? message;
  String? languageMessageKey;
  int? noOfRating;
  int? noOfReviews;

  String? star1;
  String? star2;
  String? star3;
  String? star4;
  String? star5;
  String? totalImages;
  double? productRating;
  late List<RatingData> ratingData;

  ProductRating(
      {this.error,
      this.message,
      this.languageMessageKey,
      this.noOfRating,
      this.noOfReviews,
      this.star1,
      this.star2,
      this.star3,
      this.star4,
      this.star5,
      this.totalImages,
      this.productRating,
      required this.ratingData});

  ProductRating.fromJson(Map<String, dynamic> json) {
    error = json['error'];
    message = json['message'];
    languageMessageKey = json['language_message_key'];
    noOfRating = json['no_of_rating'];
    noOfReviews = json['no_of_reviews'];

    star1 = json['star_1'];
    star2 = json['star_2'];
    star3 = json['star_3'];
    star4 = json['star_4'];
    star5 = json['star_5'];
    totalImages = json['total_images'].toString();
    productRating = double.tryParse(json['product_rating'].toString());
    ratingData = <RatingData>[];
    if (json[ApiURL.dataKey] != null) {
      json[ApiURL.dataKey].forEach((v) {
        ratingData.add(RatingData.fromJson(v));
      });
    }
  }
  // Method to get a list of all images from all ratings
  Map<String, RatingData> getAllImages() {
    Map<String, RatingData> images = {};
    if (ratingData.isNotEmpty) {
      for (var rating in ratingData) {
        if (rating.images != null) {
          rating.images!.forEach((image) {
            images.addAll({image: rating});
          });
        }
      }
    }

    return images;
  }
}

class RatingData {
  int? id;
  int? userId;
  int? productId;
  double? rating;
  List<String>? images;
  String? title;
  String? comment;
  String? createdAt;
  String? updatedAt;
  String? userName;
  String? userProfile;

  RatingData(
      {this.id,
      this.userId,
      this.productId,
      this.rating,
      this.images,
      this.title,
      this.comment,
      this.createdAt,
      this.updatedAt,
      this.userName,
      this.userProfile});

  RatingData.fromJson(Map<String, dynamic> json) {
    // Define the input format
    DateFormat inputFormat = DateFormat('yyyy-MM-dd');
    // Parse the date string into a DateTime object
    DateTime dateTime = inputFormat.parse(json['created_at']);
    // Define the output format
    DateFormat outputFormat = DateFormat('MMMM dd,yyyy');
    id = json['id'];
    userId = json['user_id'];
    productId = json['product_id'];
    rating = double.parse((json['rating'] ?? 0.0).toString());
    images = json['images'].cast<String>();
    title = json['title'];
    comment = json['comment'];
    createdAt = outputFormat.format(dateTime);

    updatedAt = json['updated_at'];
    userName = json['user_name'];
    userProfile = json['user_profile'];
  }
}
