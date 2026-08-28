import 'package:eshop_plus/ui/profile/customerSupport/models/ticket.dart';

import 'package:eshop_plus/ui/profile/customerSupport/repositories/ticketRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class AddOrEditTicketState {}

class AddOrEditTicketInitial extends AddOrEditTicketState {}

class AddOrEditTicketProgress extends AddOrEditTicketState {}

class AddOrEditTicketSuccess extends AddOrEditTicketState {
  final Ticket ticket;
  final String successMessage;
  AddOrEditTicketSuccess({
    required this.ticket,
    required this.successMessage,
  });
}

class AddOrEditTicketFailure extends AddOrEditTicketState {
  final String errorMessage;

  AddOrEditTicketFailure(this.errorMessage);
}

class AddOrEditTicketCubit extends Cubit<AddOrEditTicketState> {
  final TicketRepository _ticketRepository = TicketRepository();

  AddOrEditTicketCubit() : super(AddOrEditTicketInitial());

  void addOrEditTicket(
      {required Map<String, dynamic> params, required bool isEdit}) async {
    emit(AddOrEditTicketProgress());
    _ticketRepository
        .addOrEditTicket(params: params, isEdit: isEdit)
        .then((value) {
      emit(AddOrEditTicketSuccess(
          ticket: value.ticket, successMessage: value.successMessage));
    }).catchError((e) {
      emit(AddOrEditTicketFailure(e.toString()));
    });
  }
}
