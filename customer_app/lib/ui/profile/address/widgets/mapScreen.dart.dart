import 'dart:async';
import 'package:eshop_plus/commons/widgets/customAppbar.dart';
import 'package:eshop_plus/commons/widgets/customRoundedButton.dart';
import 'package:eshop_plus/commons/widgets/customTextContainer.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:eshop_plus/utils/designConfig.dart';
import 'package:flutter/material.dart';
import 'package:geocoding/geocoding.dart';
import 'package:get/get.dart';

import 'package:google_maps_flutter/google_maps_flutter.dart';

import '../../../../utils/utils.dart';
import 'myMarker.dart';

class MapScreen extends StatefulWidget {
  final double? latitude, longitude;
  final String? from;

  const MapScreen({Key? key, this.latitude, this.longitude, this.from})
      : super(key: key);
  static Widget getRouteInstance() {
    final arguments = Get.arguments as Map<String, dynamic>;
    return MapScreen(
      latitude: arguments['latitude'],
      longitude: arguments['longitude'],
    );
  }

  @override
  _MapScreenState createState() => _MapScreenState();
}

class _MapScreenState extends State<MapScreen> {
  LatLng? latlong;
  late CameraPosition _cameraPosition;

  GoogleMapController? _controller;
  TextEditingController locationController = TextEditingController();
  Set<Marker> _markers = {};

  Future getCurrentLocation() async {
    try {
      List<Placemark> placemark =
          await placemarkFromCoordinates(widget.latitude!, widget.longitude!);

      latlong = LatLng(widget.latitude!, widget.longitude!);

      _cameraPosition = CameraPosition(target: latlong!, zoom: 16.0);

      if (_controller != null) {
        _controller!.animateCamera(
          CameraUpdate.newCameraPosition(
            _cameraPosition,
          ),
        );
      }

      var address;
      address = placemark[0].name;
      address = address + ',' + placemark[0].subLocality;
      address = address + ',' + placemark[0].locality;
      address = address + ',' + placemark[0].administrativeArea;
      address = address + ',' + placemark[0].country;
      address = address + ',' + placemark[0].postalCode;

      locationController.text = address;
      _markers.add(
        Marker(
          markerId: const MarkerId('Marker'),
          position: LatLng(
            widget.latitude!,
            widget.longitude!,
          ),
        ),
      );
      setState(() {});
    } catch (e) {}
  }

  @override
  void initState() {
    super.initState();

    _cameraPosition = const CameraPosition(target: LatLng(0, 0), zoom: 10.0);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      getCurrentLocation();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const CustomAppbar(titleKey: chooseLocationKey),
      body: SafeArea(
        child: Column(
          children: <Widget>[
            Expanded(
              child: Stack(
                children: [
                  (latlong != null)
                      ? GoogleMap(
                          initialCameraPosition: _cameraPosition,
                          onMapCreated: (GoogleMapController controller) {
                            setState(() {
                              _controller = controller;
                            });
                            _controller!.animateCamera(
                              CameraUpdate.newCameraPosition(
                                  CameraPosition(target: latlong!, zoom: 16.0)),
                            );
                          },
                          myLocationButtonEnabled: false,
                          minMaxZoomPreference:
                              const MinMaxZoomPreference(0, 16),
                          markers: _markers,
                          onTap: (latLng) {
                            if (mounted) {
                              setState(() {
                                latlong = latLng;
                                // Update the marker's position
                                _markers.clear();
                                myMarker(_markers, latlong!, setState,
                                    locationController);
                              });
                            }
                          },
                        )
                      : const SizedBox(),
                ],
              ),
            ),
            DesignConfig.smallHeightSizedBox,
            Row(
              children: <Widget>[
                const Icon(
                  Icons.location_on,
                  color: Colors.green,
                ),
                const SizedBox(
                  width: 8,
                ),
                Expanded(
                  child: CustomTextContainer(
                    textKey: locationController.text,
                    overflow: TextOverflow.visible,
                    style: Theme.of(context).textTheme.bodyLarge,
                  ),
                )
              ],
            ),
            Padding(
                padding:
                    const EdgeInsetsDirectional.only(bottom: 25.0, top: 10),
                child: CustomRoundedButton(
                  widthPercentage: 0.5,
                  buttonTitle: updateLocationKey,
                  showBorder: false,
                  onTap: () {
                    Future.delayed(
                      const Duration(milliseconds: 200),
                      () => Utils.popNavigation(context, result: {
                        latitudeKey: latlong!.latitude.toString(),
                        longitudeKey: latlong!.longitude.toString(),
                      }),
                    );
                  },
                )),
          ],
        ),
      ),
    );
  }
}
