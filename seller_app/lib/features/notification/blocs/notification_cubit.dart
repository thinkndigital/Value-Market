import 'package:eshopplus_seller/features/notification/models/notification.dart';
import 'package:eshopplus_seller/features/notification/repositories/notificationRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class NotificationState {}

class NotificationInitial extends NotificationState {}

class NotificationFetchInProgress extends NotificationState {
  NotificationFetchInProgress();
}

class NotificationFetchSuccess extends NotificationState {
  final int total;
  final List<Notifications> notifications;
  final bool fetchMoreError;
  final bool fetchMoreInProgress;

  NotificationFetchSuccess({
    required this.notifications,
    required this.fetchMoreError,
    required this.fetchMoreInProgress,
    required this.total,
  });

  NotificationFetchSuccess copyWith({
    bool? fetchMoreError,
    bool? fetchMoreInProgress,
    int? total,
    List<Notifications>? notifications,
  }) {
    return NotificationFetchSuccess(
      notifications: notifications ?? this.notifications,
      fetchMoreError: fetchMoreError ?? this.fetchMoreError,
      fetchMoreInProgress: fetchMoreInProgress ?? this.fetchMoreInProgress,
      total: total ?? this.total,
    );
  }
}

class NotificationFetchFailure extends NotificationState {
  final String errorMessage;

  NotificationFetchFailure(this.errorMessage);
}

class NotificationCubit extends Cubit<NotificationState> {
  final NotificationRepository _orderRepository = NotificationRepository();

  NotificationCubit() : super(NotificationInitial());

  void getNotification(int storeId) async {
    emit(NotificationFetchInProgress());
    try {
      final result = await _orderRepository.getNotifications(storeId);
      emit(NotificationFetchSuccess(
        notifications: result.notifications,
        fetchMoreError: false,
        fetchMoreInProgress: false,
        total: result.total,
      ));
    } catch (e) {
      emit(NotificationFetchFailure(e.toString()));
    }
  }

  List<Notifications> getNotificationList() {
    if (state is NotificationFetchSuccess) {
      return (state as NotificationFetchSuccess).notifications;
    }
    return [];
  }

  bool fetchMoreError() {
    if (state is NotificationFetchSuccess) {
      return (state as NotificationFetchSuccess).fetchMoreError;
    }
    return false;
  }

  bool hasMore() {
    if (state is NotificationFetchSuccess) {
      return (state as NotificationFetchSuccess).notifications.length <
          (state as NotificationFetchSuccess).total;
    }
    return false;
  }

  void loadMore(int storeId) async {
    if (state is NotificationFetchSuccess) {
      if ((state as NotificationFetchSuccess).fetchMoreInProgress) {
        return;
      }
      try {
        emit((state as NotificationFetchSuccess)
            .copyWith(fetchMoreInProgress: true));

        final moreNotification = await _orderRepository.getNotifications(
            storeId,
            offset: (state as NotificationFetchSuccess).notifications.length);

        final currentState = (state as NotificationFetchSuccess);

        List<Notifications> notifications = currentState.notifications;

        notifications.addAll(moreNotification.notifications);

        emit(NotificationFetchSuccess(
          fetchMoreError: false,
          fetchMoreInProgress: false,
          total: moreNotification.total,
          notifications: notifications,
        ));
      } catch (e) {
        emit((state as NotificationFetchSuccess)
            .copyWith(fetchMoreInProgress: false, fetchMoreError: true));
      }
    }
  }
}
