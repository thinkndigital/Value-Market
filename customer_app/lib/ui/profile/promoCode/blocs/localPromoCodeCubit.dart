import 'package:eshop_plus/ui/profile/promoCode/models/promoCode.dart';
import 'package:eshop_plus/core/constants/hiveConstants.dart';
import 'package:hive/hive.dart';

class LocalPromocodeCubit {
  final Box promoBox = Hive.box(promocodeBoxKey);
  void applyPromocode({required int storeId, required PromoCode promoCode}) {
    promoBox.put(storeId, promoCode.toMap());
  }

  void removePromocode(int storeId) async {
    // Check if the favorite exists in the local storage
    if (promoBox.containsKey(storeId)) {
      await promoBox.delete(storeId); // Remove the favorite by id
    }
  }

  Future<PromoCode?> getPromocode(int storeId) async {
    if (promoBox.containsKey(storeId)) {
      return PromoCode.fromJson(promoBox.get(storeId));
    }
    return null;
  }
}
