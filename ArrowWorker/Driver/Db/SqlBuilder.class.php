<?php
/**
 * Created by PhpStorm.
 * User: louis
 * Date: 11/6/17
 * Time: 11:23 AM
 */

namespace ArrowWorker\Driver\Db;



use ArrowWorker\Driver\Db;

class SqlBuilder
{
    private $where  = "";
    private $column = "*";
    private $table  = "";
    private $limit  = "";
    private $orderBy = "";
    private $groupBy = "";
    private $having  = "";

    public function __construct($instance=null)
    {
        //todo
    }


    public function Where($where)
    {
        $this->where  = ($where != '') ? " where {$where} " : '';
        return $this;
    }

    public function Table($table)
    {
        $this->table = $table;
        return $this;
    }

    public function Col($column)
    {
        $this->column  = ( $column=="" ) ? "*" : implode(',', $column);
        return $this;
    }

    public function Limit($start, $num)
    {
        $this->limit = " limit {$start},{$num} ";
        return $this;
    }

    public function OrderBy($orderBy)
    {
        $this->orderBy = $orderBy;
        return $this;
    }

    public function GroupBy($groupBy)
    {
        $this->groupBy = ($groupBy != "") ? " group by {$groupBy} " : '' ;
        return $this;
    }

    public function Having($having)
    {
        $this->having  = ($having != "") ? " having {$having} " : '';
        return $this;
    }

    public function Find()
    {
        $result =  Db::getDb()->query( $this->parseSelect() );
        return [
            'sql'  => $this->parseSelect(),
            'data' => $result
        ];
    }

    public function Get()
    {
        $result = Db::getDb()->query( $this->parseSelect() );
        return [
            'sql'  => $this->parseSelect(),
            'data' => $result ? $result[0] : $result
        ];
    }

    public function Insert($data)
    {
        if ( !is_array($data) )
        {
            throw new \Exception("inert data must be an array,just like ['name'=>'Louis']");
        }
        $column = '';
        $values = '';
        foreach ($data as $key=>$val)
        {
            $column = $column.$key.",";
            $values = $values."'".$val."',";
        }
        $column = substr($column,0,-1);
        $values = substr($values,0,-1);
        return Db::getDb()->execute("insert into {$this->table}({$column}) values({$values})");
    }

    public function Update($data)
    {
        if ( !is_array($data) )
        {
            throw new \Exception("update data must be an array,just like ['name'=>'Louis']");
        }
        $update = '';
        foreach ($data as $key=>$val)
        {
            $update = $update.$key."='".$val."',";
        }
        $update = substr($update,0,-1);
        return Db::getDb()->execute("update {$this->table} set {$update} {$this->where}");
    }

    public function delete()
    {
        return Db::getDb()->execute("delete from {$this->table} {$this->where}");
    }

    public function parseSelect()
    {
        if ( $this->table=="" )
        {
            throw new \Exception("please specify the table you wanna query.");
        }
        return trim("select  {$this->column} from {$this->table} {$this->where} {$this->groupBy} {$this->having} {$this->limit}");
    }


}