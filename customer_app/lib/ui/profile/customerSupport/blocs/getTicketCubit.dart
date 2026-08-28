import 'package:eshop_plus/ui/profile/customerSupport/models/ticket.dart';
import 'package:eshop_plus/ui/profile/customerSupport/repositories/ticketRepository.dart';

import 'package:flutter_bloc/flutter_bloc.dart';

abstract class TicketState {}

class TicketInitial extends TicketState {}

class TicketFetchInProgress extends TicketState {}

class TicketFetchSuccess extends TicketState {
  final int total;
  final List<Ticket> tickets;
  final bool fetchMoreError;
  final bool fetchMoreInProgress;

  TicketFetchSuccess({
    required this.tickets,
    required this.fetchMoreError,
    required this.fetchMoreInProgress,
    required this.total,
  });

  TicketFetchSuccess copyWith({
    bool? fetchMoreError,
    bool? fetchMoreInProgress,
    int? total,
    List<Ticket>? tickets,
  }) {
    return TicketFetchSuccess(
      tickets: tickets ?? this.tickets,
      fetchMoreError: fetchMoreError ?? this.fetchMoreError,
      fetchMoreInProgress: fetchMoreInProgress ?? this.fetchMoreInProgress,
      total: total ?? this.total,
    );
  }
}

class TicketFetchFailure extends TicketState {
  final String errorMessage;

  TicketFetchFailure(this.errorMessage);
}

class TicketCubit extends Cubit<TicketState> {
  final TicketRepository _ticketRepository = TicketRepository();

  TicketCubit() : super(TicketInitial());

  void getTickets() async {
    emit(TicketFetchInProgress());
    try {
      final result = await _ticketRepository.getTickets();
      emit(TicketFetchSuccess(
        tickets: result.tickets,
        fetchMoreError: false,
        fetchMoreInProgress: false,
        total: result.total,
      ));
    } catch (e) {
      emit(TicketFetchFailure(e.toString()));
    }
  }

  List<Ticket> getTicketList() {
    if (state is TicketFetchSuccess) {
      return (state as TicketFetchSuccess).tickets;
    }
    return [];
  }

  bool fetchMoreError() {
    if (state is TicketFetchSuccess) {
      return (state as TicketFetchSuccess).fetchMoreError;
    }
    return false;
  }

  bool hasMore() {
    if (state is TicketFetchSuccess) {
      return (state as TicketFetchSuccess).tickets.length <
          (state as TicketFetchSuccess).total;
    }
    return false;
  }

  void loadMore() async {
    if (state is TicketFetchSuccess) {
      if ((state as TicketFetchSuccess).fetchMoreInProgress) {
        return;
      }
      try {
        emit((state as TicketFetchSuccess).copyWith(fetchMoreInProgress: true));

        final moreTicket = await _ticketRepository.getTickets(
            offset: (state as TicketFetchSuccess).tickets.length);

        final currentState = (state as TicketFetchSuccess);

        List<Ticket> tickets = currentState.tickets;

        tickets.addAll(moreTicket.tickets);

        emit(TicketFetchSuccess(
          fetchMoreError: false,
          fetchMoreInProgress: false,
          total: moreTicket.total,
          tickets: tickets,
        ));
      } catch (e) {
        emit((state as TicketFetchSuccess)
            .copyWith(fetchMoreInProgress: false, fetchMoreError: true));
      }
    }
  }

  emitSuccessState(List<Ticket> tickets, int total) {
    if (state is! TicketFetchSuccess) {
      emit(TicketFetchSuccess(
        tickets: tickets,
        fetchMoreError: false,
        fetchMoreInProgress: false,
        total: total,
      ));
    } else {
      emit((state as TicketFetchSuccess)
          .copyWith(tickets: tickets, total: total));
    }
  }
}
