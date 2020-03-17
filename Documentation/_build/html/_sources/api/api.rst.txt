Api
===

:class:`Api`

:Qualified name: ``Vinou\ApiConnector\Api``

.. php:class:: Api

  .. php:method:: __construct ([])

    :param string $token:
      token created in token module in https://app.vinou.de.
      Default: ``false``
    :param string $authid:
      authid from invoice config in settings area in https://app.vinou.de.
      Default: ``false``
    :param boolean $logging:
      enable logging if needed.
      Default: ``false``
    :param boolean $dev:
      enable dev mode.
      Default: ``false``

  .. php:method:: activateClient ([])

    :param $postData:
      Default: ``NULL``

  .. php:method:: addItemToBasket ([])

    :param $postData:
      Default: ``NULL``

  .. php:method:: addOrder ([])

    :param $order:
      Default: ``null``

  .. php:method:: cancelPaypalPayment ([])

    :param $postData:
      Default: ``[]``

  .. php:method:: checkClientMail ([])

    :param $postData:
      Default: ``NULL``

  .. php:method:: clientLogin ([])

    :param $postData:
      Default: ``NULL``

  .. php:method:: clientLogout ([])

    :param $postData:
      Default: ``NULL``

  .. php:method:: createBasket ()


  .. php:method:: deleteItemFromBasket ($id)

    :param $id:

  .. php:method:: editClient ([])

    :param $data:
      Default: ``NULL``

  .. php:method:: editItemInBasket ([])

    :param $postData:
      Default: ``NULL``

  .. php:method:: fetchLokalIP ()


  .. php:method:: findPackage ($type, $count)

    :param $type:
    :param $count:

  .. php:method:: finishPaypalPayment ([])

    :param $data:
      Default: ``NULL``

  .. php:method:: getAllPackages ()


  .. php:method:: getAvailablePayments ()


  .. php:method:: getBasket ([])

    :param $uuid:
      Default: ``NULL``

  .. php:method:: getBasketPackage ()


  .. php:method:: getBasketSummary ()


  .. php:method:: getCategoriesAll ([])

    :param $postData:
      Default: ``NULL``

  .. php:method:: getCategory ([])

    :param $postData:
      Default: ``NULL``

  .. php:method:: getCategoryWines ([])

    :param $postData:
      Default: ``NULL``

  .. php:method:: getCategoryWithWines ([])

    :param $postData:
      Default: ``NULL``

  .. php:method:: getClient ([])

    :param $postData:
      Default: ``NULL``

  .. php:method:: getClientOrder ([])

    :param $postData:
      Default: ``NULL``

  .. php:method:: getClientOrders ([])

    :param $postData:
      Default: ``NULL``

  .. php:method:: getCustomer ()


  .. php:method:: getExpertise ($id)

    :param $id:

  .. php:method:: getInternalNewsAll ([])

    :param $postData:
      Default: ``NULL``

  .. php:method:: getNews ([])

    :param $postData:
      Default: ``NULL``

  .. php:method:: getNewsAll ([])

    :param $postData:
      Default: ``NULL``

  .. php:method:: getOrder ([])

    :param $postData:
      Default: ``NULL``

  .. php:method:: getPasswordHash ([])

    :param $postData:
      Default: ``NULL``

  .. php:method:: getProduct ($postData)

    :param $postData:

  .. php:method:: getProductsAll ([])

    :param $postData:
      Default: ``NULL``

  .. php:method:: getSessionOrder ([])

    :param $postData:
      Default: ``[]``

  .. php:method:: getText ([])

    :param $postData:
      Default: ``NULL``

  .. php:method:: getTextsAll ([])

    :param $postData:
      Default: ``NULL``

  .. php:method:: getWine ($input)

    :param $input:

  .. php:method:: getWineriesAll ([])

    :param $postData:
      Default: ``NULL``

  .. php:method:: getWinery ([])

    :param $postData:
      Default: ``NULL``

  .. php:method:: getWinesAll ([])

    :param $postData:
      Default: ``NULL``

  .. php:method:: getWinesByCategory ($postData)

    :param $postData:

  .. php:method:: getWinesByType ($type)

    :param $type:

  .. php:method:: getWinesLatest ([])

    :param $postData:
      Default: ``NULL``

  .. php:method:: initBasket ()


  .. php:method:: loadLocalization ([])

    :param $countrycode:
      Default: ``'de'``

  .. php:method:: registerClient ([])

    :param $data:
      Default: ``NULL``

  .. php:method:: resetPassword ([])

    :param $data:
      Default: ``NULL``

  .. php:method:: searchWine ([])

    :param $postData:
      Default: ``[]``

  .. php:method:: validatePasswordHash ([])

    :param $postData:
      Default: ``NULL``

