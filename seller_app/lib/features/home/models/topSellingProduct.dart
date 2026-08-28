class TopSellingProduct {
  String? image;
  String? name;
  String? totalSold;

  TopSellingProduct({this.image, this.name, this.totalSold});

  TopSellingProduct.fromJson(Map<String, dynamic> json) {
    image = json['image'];
    name = json['name'];
    totalSold = json['total_sold'];
  }
}
