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
    * @param string $strMessage
    * @param int|null $intCode
    * @return \Exception
    */
   public static function build($strMessage, $intCode = null) {

      switch ($intCode) {

         case self::CODE_MYSQL_GONE_AWAY:
            return new DatabaseConnectionException($strMessage, $intCode);

         default:
            return new \Exception($strMessage, $intCode);

      }

   }
}
