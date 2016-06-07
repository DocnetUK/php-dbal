<?php
/**
 * Factory.php
 *
 * @author David Wilcock (dwilcock@doc-net.com)
 * @copyright Venditan Limited 2016
 */

namespace Docnet\DB\Exception;

class Factory
{

   const CODE_MYSQL_GONE_AWAY = 2006;

   /**
    * @param $strMessage
    * @param null $intCode
    * @throws DatabaseConnectionException
    * @throws \Exception
    */
   public static function build($strMessage, $intCode = null) {

      switch ($intCode) {

         case self::CODE_MYSQL_GONE_AWAY:
            throw new DatabaseConnectionException($strMessage, $intCode);

         default:
            throw new \Exception($strMessage, $intCode);

      }

   }
}