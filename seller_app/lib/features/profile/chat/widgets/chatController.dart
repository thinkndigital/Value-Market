import 'package:eshopplus_seller/main.dart';
import 'package:eshopplus_seller/core/routes/routes.dart';
import 'package:get/get.dart';

class ChatController extends GetxController {
  RxString currentChatUserId2 = ''.obs;

  bool showNotification(String senderId) {
    if (currentChatUserId == senderId &&
        Get.currentRoute == Routes.chatScreen) {
      // Chat screen for this user is open, don't show notification
      return false;
    }
    return true;
  }

  void openChat(String userId) {
    currentChatUserId2.value = userId;
  }

  void closeChat() {
    currentChatUserId2.value = '';
  }
}
