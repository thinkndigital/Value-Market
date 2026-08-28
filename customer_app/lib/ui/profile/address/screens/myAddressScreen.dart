import 'package:eshop_plus/commons/widgets/customDefaultContainer.dart';
import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:eshop_plus/ui/profile/address/blocs/deleteAddressCubit.dart';
import 'package:eshop_plus/ui/profile/address/blocs/getAddressCubit.dart';
import 'package:eshop_plus/ui/cart/cubits/getUserCart.dart';
import 'package:eshop_plus/ui/profile/address/models/address.dart';
import 'package:eshop_plus/commons/widgets/customBottomButtonContainer.dart';
import 'package:eshop_plus/commons/widgets/customRoundedButton.dart';
import 'package:eshop_plus/commons/widgets/customTextContainer.dart';
import 'package:eshop_plus/commons/widgets/customTextFieldContainer.dart';
import 'package:eshop_plus/commons/widgets/filterContainerForBottomSheet.dart';
import 'package:eshop_plus/utils/designConfig.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';

import '../../../../core/routes/routes.dart';
import '../../../../core/localization/labelKeys.dart';
import '../../../../utils/utils.dart';
import '../../../../commons/widgets/customAppbar.dart';
import '../../../../commons/widgets/customCircularProgressIndicator.dart';
import '../../../../commons/widgets/error_screen.dart';

class MyAddressScreen extends StatefulWidget {
  final bool isFromCartScreen;
  final Function(int)? onInstAdded;
  final Function(Address)? onAddressSelection;
  const MyAddressScreen(
      {Key? key,
      this.isFromCartScreen = false,
      this.onInstAdded,
      this.onAddressSelection})
      : super(key: key);

  static Widget getRouteInstance() => MultiBlocProvider(
        providers: [
          BlocProvider(
            create: (context) => DeleteAddressCubit(),
          ),
        ],
        child: MyAddressScreen(
          isFromCartScreen: Get.arguments != null &&
                  Get.arguments.containsKey('isFromCartScreen')
              ? Get.arguments['isFromCartScreen'] ?? false
              : false,
          onInstAdded:
              Get.arguments != null && Get.arguments.containsKey('onInstAdded')
                  ? Get.arguments['onInstAdded']
                  : null,
          onAddressSelection: Get.arguments != null &&
                  Get.arguments.containsKey('onAddressSelection')
              ? Get.arguments['onAddressSelection']
              : null,
        ),
      );
  @override
  _MyAddressScreenState createState() => _MyAddressScreenState();
}

class _MyAddressScreenState extends State<MyAddressScreen> {
  int _selectedRadio = 0;
  late GetAddressState state;
  @override
  void initState() {
    super.initState();
    Future.delayed(Duration.zero, () {
      state = context.read<GetAddressCubit>().state;
      if (state is GetAddressFetchSuccess) {
        changeSelectedAddress(state as GetAddressFetchSuccess);
      }
      //if adrdress API is not called then call it
      if (state is GetAddressInitial) {
        getAddress();
      }
    });
  }

  getAddress() {
    context.read<GetAddressCubit>().getAddress();
  }

  changeSelectedAddress(GetAddressFetchSuccess state) {
    if (state.addresses.isNotEmpty) {
      if (widget.isFromCartScreen &&
          context.read<GetUserCartCubit>().getCartDetail().selectedAddress !=
              null) {
        _selectedRadio = state.addresses
            .firstWhere((element) =>
                element.id ==
                context
                    .read<GetUserCartCubit>()
                    .getCartDetail()
                    .selectedAddress!
                    .id)
            .id!;
      } else {
        int index =
            state.addresses.indexWhere((element) => element.isDefault == 1);
        if (index != -1) {
          context
              .read<GetUserCartCubit>()
              .changeSelectedAddress(state.addresses[index]);
          _selectedRadio = state.addresses[index].id!;
        } else {
          context
              .read<GetUserCartCubit>()
              .changeSelectedAddress(state.addresses.first);
          _selectedRadio = state.addresses.first.id!;
        }
      }
    }
    setState(() {});
  }

  @override
  dispose() {
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: !widget.isFromCartScreen
          ? const CustomAppbar(titleKey: myAddressKey)
          : null,
      bottomNavigationBar: !widget.isFromCartScreen
          ? CustomBottomButtonContainer(
              child: CustomRoundedButton(
                widthPercentage: 1.0,
                buttonTitle: addNewAddressKey,
                showBorder: false,
                onTap: () => Utils.navigateToScreen(
                    context, Routes.addNewAddressScreen,
                    arguments: {
                      'bloc': context.read<GetAddressCubit>(),
                      'isEditScreen': false,
                      'address': null,
                    }),
              ),
            )
          : null,
      body: Column(
        children: [
          Expanded(
            child: BlocConsumer<GetAddressCubit, GetAddressState>(
              listener: (context, state) {
                if (state is GetAddressFetchSuccess) {
                  changeSelectedAddress(state);
                }
              },
              builder: (context, state) {
                if (state is GetAddressFetchSuccess) {
                  return state.addresses.isNotEmpty
                      ? buildAddressList(state)
                      : returnErrorScreen(state, noAddressesKey);
                }
                if (state is GetAddressFetchFailure) {
                  return returnErrorScreen(state, state.errorMessage);
                }
                if (state is GetAddressFetchInProgress) {
                  return Center(
                    child: CustomCircularProgressIndicator(
                        indicatorColor: Theme.of(context).colorScheme.primary),
                  );
                }
                return ErrorScreen(
                    onPressed: getAddress, text: defaultErrorMessageKey);
              },
            ),
          ),
          if (widget.isFromCartScreen)
            GestureDetector(
              onTap: () => Utils.navigateToScreen(
                  context, Routes.addNewAddressScreen,
                  arguments: {
                    'bloc': context.read<GetAddressCubit>(),
                    'isEditScreen': false,
                    'address': null,
                  }),
              child: Padding(
                padding: const EdgeInsets.all(appContentHorizontalPadding),
                child: CustomDefaultContainer(
                    borderRadius: 8,
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: <Widget>[
                        Icon(Icons.add,
                            color: Theme.of(context).colorScheme.primary),
                        DesignConfig.smallWidthSizedBox,
                        CustomTextContainer(
                          textKey: addNewAddressKey,
                          style: Theme.of(context)
                              .textTheme
                              .bodyLarge!
                              .copyWith(
                                  color: Theme.of(context).colorScheme.primary),
                        )
                      ],
                    )),
              ),
            )
        ],
      ),
    );
  }

  returnErrorScreen(GetAddressState state, String errorMessage) {
    return ErrorScreen(
        onPressed: getAddress,
        text: errorMessage,
        image: errorMessage == noInternetKey ? "no_internet" : "no_address",
        child: state is GetAddressFetchInProgress
            ? CustomCircularProgressIndicator(
                indicatorColor: Theme.of(context).colorScheme.primary,
              )
            : null);
  }

  Widget buildAddressList(GetAddressFetchSuccess getAddressstate) {
    return RefreshIndicator(
      onRefresh: () async {
        getAddress();
      },
      child: BlocListener<DeleteAddressCubit, DeleteAddressState>(
        listener: (context, state) {
          if (state is DeleteAddressSuccess) {
            if (getAddressstate.addresses
                    .firstWhere((element) => element.id == state.id)
                    .isDefault ==
                1) {
              getAddress();
            }
            getAddressstate.addresses
                .removeWhere((element) => element.id == state.id);
            context
                .read<GetAddressCubit>()
                .emitSuccessState(getAddressstate.addresses);
            Utils.showSnackBar(context: context, message: state.successMessage);
          }

          if (state is DeleteAddressFailure) {
            getAddressstate.addresses
                .firstWhere((element) => element.id == state.id)
                .deleteInProgress = false;
            Utils.showSnackBar(context: context, message: state.errorMessage);
          }
        },
        child: Column(
          children: [
            Expanded(
              child: ListView.separated(
                separatorBuilder: (context, index) =>
                    DesignConfig.smallHeightSizedBox,
                itemCount: getAddressstate.addresses.length,
                padding: EdgeInsetsDirectional.symmetric(
                    vertical: widget.isFromCartScreen ? 0 : 12,
                    horizontal: appContentHorizontalPadding),
                shrinkWrap: true,
                physics: AlwaysScrollableScrollPhysics(),
                itemBuilder: (context, index) {
                  Address address = getAddressstate.addresses[index];
                  return AddressContainer(
                      address: address,
                      addresses: getAddressstate.addresses,
                      isSelected: _selectedRadio == address.id,
                      onSelect: () {
                        setState(() {
                          _selectedRadio = address.id!;
                        });
                        if (widget.isFromCartScreen)
                          widget.onAddressSelection!(address);
                      },
                      isFromCartScreen: widget.isFromCartScreen,
                      onInstAdded: widget.onInstAdded);
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class AddressContainer extends StatefulWidget {
  final Address address;
  final List<Address> addresses;
  final bool isSelected;
  final VoidCallback onSelect;
  final bool isFromCartScreen;
  final Function(int)? onInstAdded;
  const AddressContainer(
      {Key? key,
      required this.address,
      required this.addresses,
      required this.isSelected,
      required this.onSelect,
      required this.isFromCartScreen,
      this.onInstAdded})
      : super(key: key);

  @override
  _AddressContainerState createState() => _AddressContainerState();
}

class _AddressContainerState extends State<AddressContainer>
    with SingleTickerProviderStateMixin {
  late AnimationController _expandController;
  late Animation<double> _animation;
  late final Animation<double> _fadeAnimation;
  @override
  void initState() {
    super.initState();

    _expandController =
        AnimationController(vsync: this, duration: const Duration(seconds: 1));
    _animation = CurvedAnimation(
      parent: _expandController,
      curve: const Interval(
        0.0,
        0.4,
        curve: Curves.fastOutSlowIn,
      ),
    );
    _fadeAnimation = Tween(begin: 0.0, end: 1.0).animate(CurvedAnimation(
        parent: _expandController,
        curve: const Interval(
          0.4,
          0.7,
          curve: Curves.fastOutSlowIn,
        )));
    if (widget.isSelected) {
      _expandController.forward();
    }
  }

  @override
  void dispose() {
    _expandController.dispose();

    super.dispose();
  }

  @override
  void didUpdateWidget(AddressContainer oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.isSelected) {
      _expandController.forward();
    } else {
      _expandController.reverse();
    }
  }

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      key: ValueKey(widget.address.id),
      onTap: widget.onSelect,
      child: Container(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(8),
            color: Theme.of(context).colorScheme.primaryContainer,
          ),
          padding: const EdgeInsetsDirectional.symmetric(
              horizontal: appContentHorizontalPadding,
              vertical: appContentHorizontalPadding / 2),
          child: Stack(
            clipBehavior: Clip.none,
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      widget.isSelected
                          ? Icon(
                              Icons.radio_button_checked,
                              color: Theme.of(context).colorScheme.primary,
                              size: 24,
                            )
                          : Icon(
                              Icons.radio_button_unchecked,
                              color: Theme.of(context).colorScheme.secondary,
                              size: 24,
                            ),
                      DesignConfig.defaultWidthSizedBox,
                      Expanded(
                        child: Utils.getAddressWidget(context, widget.address),
                      )
                    ],
                  ),
                  SizeTransition(
                    axisAlignment: widget.isSelected ? 1.0 : 0.0,
                    sizeFactor: _animation,
                    child: FadeTransition(
                      opacity: _fadeAnimation,
                      child: Padding(
                        padding: const EdgeInsetsDirectional.only(
                            top: appContentHorizontalPadding),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                          children: <Widget>[
                            buildEditAddressButton(),
                            const SizedBox(
                              width: 14,
                            ),
                            !widget.isFromCartScreen
                                ? buildDeleteButton()
                                : addDeliveryInstructionButton()
                          ],
                        ),
                      ),
                    ),
                  )
                ],
              ),
              Positioned.directional(
                  textDirection: Directionality.of(context),
                  end: -8,
                  top: 0,
                  child: Container(
                    height: 20,
                    width: 40,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(borderRadius),
                      color: Theme.of(context)
                          .colorScheme
                          .primary
                          .withValues(alpha: 0.8),
                    ),
                    child: CustomTextContainer(
                      textKey: widget.address.type ?? '',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 10,
                        fontWeight: FontWeight.w500,
                        height: 0.12,
                      ),
                    ),
                  )),
            ],
          )),
    );
  }

  Expanded buildEditAddressButton() {
    return Expanded(
      child: CustomRoundedButton(
        widthPercentage: 0.5,
        buttonTitle: editAddressKey,
        showBorder: true,
        backgroundColor: Theme.of(context).colorScheme.onPrimary,
        borderColor: Theme.of(context).colorScheme.primary,
        style: Theme.of(context)
            .textTheme
            .bodySmall!
            .copyWith(color: Theme.of(context).colorScheme.secondary),
        onTap: () => Utils.navigateToScreen(context, Routes.addNewAddressScreen,
            arguments: {
              'bloc': context.read<GetAddressCubit>(),
              'isEditScreen': true,
              'address': widget.address,
            }),
      ),
    );
  }

  Widget buildDeleteButton() {
    return Expanded(
      child: BlocBuilder<DeleteAddressCubit, DeleteAddressState>(
        builder: (context, state) {
          return CustomRoundedButton(
            widthPercentage: 0.5,
            buttonTitle: deleteKey,
            showBorder: false,
            style: Theme.of(context)
                .textTheme
                .bodySmall!
                .copyWith(color: Theme.of(context).colorScheme.onPrimary),
            child: state is DeleteAddressInProgress &&
                    state.addresses
                            .firstWhere(
                                (element) => element.id == widget.address.id)
                            .deleteInProgress ==
                        true
                ? const CustomCircularProgressIndicator()
                : null,
            onTap: () {
              if (state is DeleteAddressInProgress &&
                  state.addresses
                          .firstWhere(
                              (element) => element.id == widget.address.id)
                          .deleteInProgress ==
                      true) return;
              Utils.openAlertDialog(
                context,
                onTapYes: () {
                  Get.back();
                  context.read<DeleteAddressCubit>().deleteAddress(
                      addressId: widget.address.id!,
                      addresses: widget.addresses);
                },
                message: areYouSureYouWantToDeleteKey,
              );
            },
          );
        },
      ),
    );
  }

  addDeliveryInstructionButton() {
    return Expanded(
      child: CustomRoundedButton(
          widthPercentage: 0.5,
          buttonTitle: addDeliveryInstructionKey,
          showBorder: true,
          backgroundColor: Theme.of(context).colorScheme.onPrimary,
          borderColor: Theme.of(context).colorScheme.primary,
          horizontalPadding: 2,
          style: Theme.of(context).textTheme.bodySmall,
          onTap: () {
            TextEditingController instructionController = TextEditingController(
                text: context
                    .read<GetUserCartCubit>()
                    .getCartDetail()
                    .deliveryInstruction);
            Utils.openModalBottomSheet(
                context,
                FilterContainerForBottomSheet(
                  title: addDeliveryInstructionKey,
                  borderedButtonTitle: '',
                  primaryButtonTitle: submitKey,
                  borderedButtonOnTap: () {},
                  primaryButtonOnTap: () {
                    if (instructionController.text.isNotEmpty) {
                      context
                          .read<GetUserCartCubit>()
                          .addDeliveryInstruction(instructionController.text);
                      Navigator.of(context).pop();
                      widget.onInstAdded?.call(1);
                    }
                  },
                  content: CustomTextFieldContainer(
                    hintTextKey: typeKey,
                    textEditingController: instructionController,
                    maxLines: 5,
                  ),
                ),
                staticContent: true,
                isScrollControlled: true);
          }),
    );
  }
}
