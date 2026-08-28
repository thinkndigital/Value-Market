class ReturnRequest {
  final int id;
  final String? reason;
  final int status;
  final String createdAt;
  final int productId;
  final String productName;
  final String productImage;
  final String productType;
  final int orderItemId;
  final String username;
  final int orderId;
  final String paymentMethod;
  final int quantity;
  final double subTotal;
  final double price;
  final double? discountedPrice;
  final String storeName;

  ReturnRequest({
    required this.id,
    this.reason,
    required this.status,
    required this.createdAt,
    required this.productId,
    required this.productName,
    required this.productImage,
    required this.productType,
    required this.orderItemId,
    required this.username,
    required this.orderId,
    required this.paymentMethod,
    required this.quantity,
    required this.subTotal,
    required this.price,
    this.discountedPrice,
    required this.storeName,
  });

  factory ReturnRequest.fromJson(Map<String, dynamic> json) {
    return ReturnRequest(
      id: json['id'],
      reason: json['reason'],
      status: json['status'],
      createdAt: json['created_at'],
      productId: json['product_id'],
      productName: json['product_name'],
      productImage: json['product_image'],
      productType: json['product_type'],
      orderItemId: json['order_item_id'],
      username: json['username'],
      orderId: json['order_id'],
      paymentMethod: json['payment_method'],
      quantity: json['quantity'],
      subTotal: double.parse((json['sub_total'] ?? 0).toString().isEmpty
          ? "0"
          : (json['sub_total'] ?? 0).toString()),
      price: double.parse((json['price'] ?? 0).toString().isEmpty
          ? "0"
          : (json['price'] ?? 0).toString()),
      discountedPrice: double.parse(
          (json['discounted_price'] ?? 0).toString().isEmpty
              ? "0"
              : (json['discounted_price'] ?? 0).toString()),
      storeName: json['store_name'],
    );
  }
}
