class PromoCode {
  int? id;
  String? promoCode;
  String? title;
  String? message;
  String? startDate;
  String? endDate;
  double? discount;
  String? repeatUsage;
  double? minOrderAmt;
  String? noOfUsers;
  String? discountType;
  double? maxDiscountAmt;
  String? image;
  String? noOfRepeatUsage;
  int? status;
  int? isCashback;
  int? listPromocode;
  int? remainingDays;
  double? finalTotal;
  double? finalDiscount;

  PromoCode(
      {this.id,
      this.promoCode,
      this.message,
      this.title,
      this.startDate,
      this.endDate,
      this.discount,
      this.repeatUsage,
      this.minOrderAmt,
      this.noOfUsers,
      this.discountType,
      this.maxDiscountAmt,
      this.image,
      this.noOfRepeatUsage,
      this.status,
      this.isCashback,
      this.listPromocode,
      this.finalDiscount,
      this.finalTotal,
      this.remainingDays});

  PromoCode.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    promoCode = json['promo_code'];
    message = json['message'];
    title = json['title'];
    startDate = json['start_date'];
    endDate = json['end_date'];
    discount = double.tryParse((json['discount'] ?? 0).toString().isEmpty
        ? "0"
        : (json['discount'] ?? 0).toString());

    repeatUsage = json['repeat_usage'].toString();
    minOrderAmt = double.tryParse(
        (json['min_order_amt'] ?? 0).toString().isEmpty
            ? "0"
            : (json['min_order_amt'] ?? 0).toString());
    noOfUsers = json['no_of_users'].toString();
    discountType = json['discount_type'];
    maxDiscountAmt = double.tryParse(
        (json['max_discount_amt'] ?? 0).toString().isEmpty
            ? "0"
            : (json['max_discount_amt'] ?? 0).toString());
    image = json['image'];
    noOfRepeatUsage = json['no_of_repeat_usage'].toString();
    status = json['status'];
    isCashback = json['is_cashback'];
    listPromocode = json['list_promocode'];
    remainingDays = json['remaining_days'];
    finalTotal = double.tryParse((json['final_total'] ?? 0).toString().isEmpty
        ? "0"
        : (json['final_total'] ?? 0).toString());

    finalDiscount = double.tryParse(
        (json['final_discount'] ?? 0).toString().isEmpty
            ? "0"
            : (json['final_discount'] ?? 0).toString());
  }

 Map<String, dynamic> toMap() {
    return {
      'id': id,
      'promo_code': promoCode,
      'message': message,
      'title': title,
      'start_date': startDate,
      'end_date': endDate,
      'discount': discount,
      'repeat_usage': repeatUsage,
      'min_order_amt': minOrderAmt,
      'no_of_users': noOfUsers,
      'discount_type': discountType,
      'max_discount_amt': maxDiscountAmt,
      'image': image,
      'no_of_repeat_usage': noOfRepeatUsage,
      'status': status,
      'is_cashback': isCashback,
      'list_promocode': listPromocode,
      'remaining_days': remainingDays,
      'final_total': finalTotal,
      'final_discount': finalDiscount,
    };
  }

}
