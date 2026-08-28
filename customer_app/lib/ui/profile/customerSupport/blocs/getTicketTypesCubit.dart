import 'package:eshop_plus/ui/profile/customerSupport/models/ticketType.dart';
import 'package:eshop_plus/ui/profile/customerSupport/repositories/ticketRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class TicketTypeState {}

class TicketTypeInitial extends TicketTypeState {}

class TicketTypeFetchInProgress extends TicketTypeState {}

class TicketTypeFetchSuccess extends TicketTypeState {
  final List<TicketType> ticketTypes;

  TicketTypeFetchSuccess(this.ticketTypes);
}

class TicketTypeFetchFailure extends TicketTypeState {
  final String errorMessage;

  TicketTypeFetchFailure(this.errorMessage);
}

class TicketTypeCubit extends Cubit<TicketTypeState> {
  final TicketRepository _ticketTypeRepository = TicketRepository();

  TicketTypeCubit() : super(TicketTypeInitial());

  void getTicketTypes() {
    emit(TicketTypeFetchInProgress());

    _ticketTypeRepository
        .getTicketTypes()
        .then((value) => emit(TicketTypeFetchSuccess(value)))
        .catchError((e) {
      emit(TicketTypeFetchFailure(e.toString()));
    });
  }
}
