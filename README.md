# pharmacy-dbms
A Pharmacy Database Management System built with MySQL and PHP (phpMyAdmin).
FOR TRIGGERS WE HAVE USED THE FOLLOWING QUERY TO PREVENT THE INPUT OF NEGATIVE STOCK


trigger name= prevent_negative_insert
BEGIN
  IF NEW.Quantity < 0 THEN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Quantity cannot be negative!';
  END IF;
  
  IF NEW.CostPrice < 0 THEN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'CostPrice cannot be negative!';
  END IF;
END


trigger name=prevent_negative_update
BEGIN
  IF NEW.Quantity < 0 THEN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Quantity cannot be negative!';
  END IF;
  
  IF NEW.CostPrice < 0 THEN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'CostPrice cannot be negative!';
  END IF;
END
